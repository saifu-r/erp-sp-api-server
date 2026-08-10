<?php

namespace App\Services;

use App\Models\Accounts\Account;
use App\Models\Sales\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(private JournalPostingService $journal) {}

    /**
     * Can be created from an existing Order (order_id set) or directly (order_id null).
     * $products only needed when creating directly — when order_id is set, line items
     * are copied from the Order automatically.
     */
    public function createInvoice(?int $orderId, ?int $customerId, ?string $date, ?array $products, float $discountPercent, float $vatPercent, ?int $userId): Transaction
    {
        return DB::transaction(function () use ($orderId, $customerId, $date, $products, $discountPercent, $vatPercent, $userId) {
            $order = null;
            if ($orderId) {
                /** @var Transaction $order */
                $order = Transaction::with('items')->findOrFail($orderId);
                $customerId = $order->customer_id;
                $date = $date ?? $order->date;
                $discountPercent = $order->discount_percent;
                $vatPercent = $order->vat_percent;
                $products = $order->items->map(fn($i) => [
                    'product_id' => $i->product_id,
                    'quantity' => $i->quantity,
                    'unit_price' => $i->cost_or_price,
                ])->toArray();
            }
            $subtotal = 0;
            $totalCogs = 0;
            $lineItems = [];

            foreach ($products as $line) {
                $product = Product::with('productItems.item')->findOrFail($line['product_id']);
                $qty = $line['quantity'];
                $unitPrice = $line['unit_price'] ?? $product->sale_price;
                $lineSubtotal = $unitPrice * $qty;
                $subtotal += $lineSubtotal;

                $lineItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'cost_or_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                ];

                foreach ($product->productItems as $productItem) {
                    $item = $productItem->item;
                    $requiredQty = $productItem->quantity_required * $qty;

                    if ($item->stock_quantity < $requiredQty) {
                        throw new \Exception("Insufficient stock for item '{$item->name}'. Available: {$item->stock_quantity}, needed: {$requiredQty}");
                    }

                    $totalCogs += $requiredQty * $item->avg_cost_per_unit;
                    $item->stock_quantity -= $requiredQty;
                    $item->save();
                }
            }

            $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            $taxableAmount = $subtotal - $discountAmount;
            $vatAmount = round($taxableAmount * ($vatPercent / 100), 2);
            $totalAmount = $taxableAmount + $vatAmount;

            $invoice = Transaction::create([
                'type' => 'invoice',
                'reference_no' => $this->generateReferenceNo(),
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'date' => $date,
                'subtotal' => $subtotal,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'vat_percent' => $vatPercent,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'payment_status' => 1, // Pending
                'status' => 1,
            ]);

            foreach ($lineItems as $line) {
                $invoice->items()->create($line);
            }

            if ($order) {
                $order->update(['invoice_status' => 2, 'payment_status' => 1]); // mirror: now Pending
            }

            $accountsReceivable = Account::where('code', '1300')->firstOrFail();
            $salesRevenue = Account::where('code', '4100')->firstOrFail();
            $vatPayable = Account::where('code', '2200')->firstOrFail();

            $journalLines = [
                ['account_id' => $accountsReceivable->id, 'debit' => $totalAmount, 'credit' => 0],
                ['account_id' => $salesRevenue->id, 'debit' => 0, 'credit' => $taxableAmount],
            ];
            if ($vatAmount > 0) {
                $journalLines[] = ['account_id' => $vatPayable->id, 'debit' => 0, 'credit' => $vatAmount];
            }

            $this->journal->post(
                description: "Invoice {$invoice->reference_no}",
                lines: $journalLines,
                referenceType: 'invoice',
                referenceId: $invoice->id,
                date: $date
            );

            if ($totalCogs > 0) {
                $cogs = Account::where('code', '5100')->firstOrFail();
                $finishedGoodsInventory = Account::where('code', '1500')->firstOrFail();

                $this->journal->post(
                    description: "COGS for {$invoice->reference_no}",
                    lines: [
                        ['account_id' => $cogs->id, 'debit' => $totalCogs, 'credit' => 0],
                        ['account_id' => $finishedGoodsInventory->id, 'debit' => 0, 'credit' => $totalCogs],
                    ],
                    referenceType: 'invoice_cogs',
                    referenceId: $invoice->id,
                    date: $date
                );
            }

            return $invoice->fresh(['items.product', 'payments', 'customer', 'order']);
        });
    }

    public function recordPayment(Transaction $invoice, float $amount, string $date, ?string $method, ?string $note): Transaction
    {
        return DB::transaction(function () use ($invoice, $amount, $date, $method, $note) {
            $invoice->payments()->create(['amount' => $amount, 'date' => $date, 'method' => $method, 'note' => $note]);

            $newPaidAmount = $invoice->paid_amount + $amount;
            $paymentStatus = $newPaidAmount >= $invoice->total_amount ? 3 : ($newPaidAmount > 0 ? 2 : 1);

            $invoice->update(['paid_amount' => $newPaidAmount, 'payment_status' => $paymentStatus]);

            // Mirror onto the originating Order, if any
            if ($invoice->order_id) {
                Transaction::where('id', $invoice->order_id)->update(['payment_status' => $paymentStatus]);
            }

            $accountsReceivable = Account::where('code', '1300')->firstOrFail();
            $paidFromAccount = Account::where('name', $method === 'bank' ? 'Bank' : 'Cash')->firstOrFail();

            $this->journal->post(
                description: "Payment for {$invoice->reference_no}",
                lines: [
                    ['account_id' => $paidFromAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $accountsReceivable->id, 'debit' => 0, 'credit' => $amount],
                ],
                referenceType: 'invoice_payment',
                referenceId: $invoice->id,
                date: $date
            );

            return $invoice->fresh(['payments']);
        });
    }

    private function generateReferenceNo(): string
    {
        $last = Transaction::where('type', 'invoice')
            ->where('reference_no', 'like', 'INV-%')
            ->orderByRaw('CAST(SUBSTRING(reference_no, 5) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = $last ? ((int) substr($last->reference_no, 4)) + 1 : 1;
        return 'INV-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

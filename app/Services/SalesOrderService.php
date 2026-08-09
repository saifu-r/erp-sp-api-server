<?php

namespace App\Services;

use App\Models\Accounts\Account;
use App\Models\Sales\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(private JournalPostingService $journal) {}

    /**
     * $products = [ ['product_id' => 1, 'quantity' => 5, 'unit_price' => 120], ... ]
     * unit_price is editable — defaults to the product's sale_price on the frontend, but not enforced here.
     */
    public function createOrder(
        int $customerId,
        string $date,
        array $products,
        float $discountPercent = 0,
        float $vatPercent = 0,
        ?int $quotationId = null,
        ?int $userId = null
    ): Transaction {
        return DB::transaction(function () use ($customerId, $date, $products, $discountPercent, $vatPercent, $quotationId, $userId) {
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
                    'raw_material_id' => null,
                    'item_id' => null,
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

            $transaction = Transaction::create([
                'type' => 'order',
                'reference_no' => $this->generateReferenceNo('ORD', 'order'),
                'quotation_id' => $quotationId,
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
                'payment_status' => 1,
                'invoice_status' => 1, // Not Invoiced yet
                'status' => 1,
            ]);

            foreach ($lineItems as $line) {
                $transaction->items()->create($line);
            }

            if ($quotationId) {
                Transaction::where('id', $quotationId)->update(['order_status' => 2]); // Converted
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
                description: "Sale {$transaction->reference_no}",
                lines: $journalLines,
                referenceType: 'order',
                referenceId: $transaction->id,
                date: $date
            );

            if ($totalCogs > 0) {
                $cogs = Account::where('code', '5100')->firstOrFail();
                $finishedGoodsInventory = Account::where('code', '1500')->firstOrFail();

                $this->journal->post(
                    description: "COGS for {$transaction->reference_no}",
                    lines: [
                        ['account_id' => $cogs->id, 'debit' => $totalCogs, 'credit' => 0],
                        ['account_id' => $finishedGoodsInventory->id, 'debit' => 0, 'credit' => $totalCogs],
                    ],
                    referenceType: 'order_cogs',
                    referenceId: $transaction->id,
                    date: $date
                );
            }

            return $transaction->fresh(['items.product', 'payments', 'customer']);
        });
    }

    public function markInvoiced(Transaction $order): Transaction
    {
        if ($order->invoice_status === 2) {
            return $order; // already invoiced — idempotent, just return as-is
        }

        $order->update([
            'invoice_status' => 2,
            'invoice_reference_no' => $this->generateReferenceNo('INV', 'order', 'invoice_reference_no'),
            'invoiced_at' => now(),
        ]);

        return $order->fresh();
    }

    private function generateReferenceNo(string $prefix, string $type, string $column = 'reference_no'): string
    {
        $last = Transaction::where('type', $type)
            ->whereNotNull($column)
            ->where($column, 'like', "{$prefix}-%")
            ->orderByRaw("CAST(SUBSTRING({$column}, " . (strlen($prefix) + 2) . ") AS UNSIGNED) DESC")
            ->first();

        $nextNumber = $last ? ((int) substr($last->{$column}, strlen($prefix) + 1)) + 1 : 1;
        return "{$prefix}-" . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
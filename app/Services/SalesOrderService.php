<?php

namespace App\Services;

use App\Models\Accounts\Account;
use App\Models\Manufacture\Item;
use App\Models\Sales\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(private JournalPostingService $journal) {}

    /**
     * $products = [ ['product_id' => 1, 'quantity' => 5], ... ]
     */
    public function createOrder(int $customerId, string $date, array $products, ?int $userId = null): Transaction
    {
        return DB::transaction(function () use ($customerId, $date, $products, $userId) {
            $totalAmount = 0;
            $totalCogs = 0;
            $lineItems = [];

            foreach ($products as $line) {
                $product = Product::with('productItems.item')->findOrFail($line['product_id']);
                $qty = $line['quantity'];
                $lineSubtotal = $product->sale_price * $qty;
                $totalAmount += $lineSubtotal;

                $lineItems[] = [
                    'raw_material_id' => null,
                    'item_id' => null, // this line represents a Product, not a single Item — see note below
                    'quantity' => $qty,
                    'cost_or_price' => $product->sale_price,
                    'subtotal' => $lineSubtotal,
                ];

                // Deduct stock for every Item that makes up this Product, and accumulate COGS
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

            $transaction = Transaction::create([
                'type' => 'order',
                'reference_no' => $this->generateReferenceNo(),
                'customer_id' => $customerId,
                'user_id' => $userId,
                'date' => $date,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'payment_status' => 1,
                'status' => 1,
            ]);

            foreach ($lineItems as $line) {
                $transaction->items()->create($line);
            }

            // Auto-post TWO journal entries: revenue recognition + cost of goods sold
            $accountsReceivable = Account::where('code', '1300')->firstOrFail();
            $salesRevenue = Account::where('code', '4100')->firstOrFail();
            $cogs = Account::where('code', '5100')->firstOrFail();
            $finishedGoodsInventory = Account::where('code', '1500')->firstOrFail();

            $this->journal->post(
                description: "Sale {$transaction->reference_no}",
                lines: [
                    ['account_id' => $accountsReceivable->id, 'debit' => $totalAmount, 'credit' => 0],
                    ['account_id' => $salesRevenue->id, 'debit' => 0, 'credit' => $totalAmount],
                ],
                referenceType: 'order',
                referenceId: $transaction->id,
                date: $date
            );

            if ($totalCogs > 0) {
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

            return $transaction->fresh(['items', 'payments', 'customer']);
        });
    }

    // private function generateReferenceNo(): string
    // {
    //     $last = Transaction::where('type', 'order')->orderBy('id', 'desc')->first();
    //     $nextNumber = $last ? ((int) substr($last->reference_no, 4)) + 1 : 1;
    //     return 'ORD-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    // }
    private function generateReferenceNo(): string
    {
        $last = Transaction::where('type', 'order')
            ->where('reference_no', 'like', 'ORD-%')
            ->orderByRaw('CAST(SUBSTRING(reference_no, 5) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = $last ? ((int) substr($last->reference_no, 4)) + 1 : 1;
        return 'ORD-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
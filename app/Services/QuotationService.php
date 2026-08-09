<?php

namespace App\Services;

use App\Models\Sales\Product;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function createQuotation(int $customerId, string $date, array $products, float $discountPercent = 0, float $vatPercent = 0, ?int $userId = null): Transaction
    {
        return DB::transaction(function () use ($customerId, $date, $products, $discountPercent, $vatPercent, $userId) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($products as $line) {
                $product = Product::findOrFail($line['product_id']);
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
            }

            $discountAmount = round($subtotal * ($discountPercent / 100), 2);
            $taxableAmount = $subtotal - $discountAmount;
            $vatAmount = round($taxableAmount * ($vatPercent / 100), 2);
            $totalAmount = $taxableAmount + $vatAmount;

            $transaction = Transaction::create([
                'type' => 'quotation',
                'reference_no' => $this->generateReferenceNo(),
                'customer_id' => $customerId,
                'user_id' => $userId,
                'date' => $date,
                'subtotal' => $subtotal,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'vat_percent' => $vatPercent,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'order_status' => 1, // Pending
                'status' => 1,
            ]);

            foreach ($lineItems as $line) {
                $transaction->items()->create($line);
            }

            return $transaction->fresh(['items.product', 'customer']);
        });
    }

    private function generateReferenceNo(): string
    {
        $last = Transaction::where('type', 'quotation')
            ->where('reference_no', 'like', 'QUO-%')
            ->orderByRaw('CAST(SUBSTRING(reference_no, 5) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = $last ? ((int) substr($last->reference_no, 4)) + 1 : 1;
        return 'QUO-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
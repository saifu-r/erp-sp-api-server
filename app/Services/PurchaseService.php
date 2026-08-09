<?php

namespace App\Services;

use App\Models\Accounts\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private RawMaterialStockService $stockService,
        private JournalPostingService $journal
    ) {}

    /**
     * $items = [ ['raw_material_id' => 1, 'quantity' => 10, 'cost_per_unit' => 500], ... ]
     * $initialPayment = float|null — if provided, records a payment immediately at purchase time
     */
    public function createPurchase(int $supplierId, string $date, array $items, ?float $initialPayment = null): Transaction
    {
        return DB::transaction(function () use ($supplierId, $date, $items, $initialPayment) {
            $totalAmount = collect($items)->sum(fn($i) => $i['quantity'] * $i['cost_per_unit']);

            $transaction = Transaction::create([
                'type' => 'purchase',
                'reference_no' => $this->generateReferenceNo(),
                'supplier_id' => $supplierId,
                'date' => $date,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'payment_status' => 1, // Pending until a payment is recorded
                'status' => 1,
            ]);

            foreach ($items as $item) {
                $subtotal = $item['quantity'] * $item['cost_per_unit'];

                $transaction->items()->create([
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity' => $item['quantity'],
                    'cost_or_price' => $item['cost_per_unit'],
                    'subtotal' => $subtotal,
                ]);

                // Real stock effect — new company-level batch, same as before
                $this->stockService->createBatch(
                    $item['raw_material_id'], 'company', null, $item['quantity'], $item['cost_per_unit']
                );
            }

            // Auto-post Journal: Debit Raw Material Inventory, Credit Accounts Payable
            $rawMaterialInventory = Account::where('code', '1400')->firstOrFail();
            $accountsPayable = Account::where('code', '2100')->firstOrFail();

            $this->journal->post(
                description: "Purchase {$transaction->reference_no} from supplier",
                lines: [
                    ['account_id' => $rawMaterialInventory->id, 'debit' => $totalAmount, 'credit' => 0],
                    ['account_id' => $accountsPayable->id, 'debit' => 0, 'credit' => $totalAmount],
                ],
                referenceType: 'purchase',
                referenceId: $transaction->id,
                date: $date
            );

            if ($initialPayment && $initialPayment > 0) {
                $this->recordPayment($transaction, $initialPayment, $date, 'cash', 'Paid at time of purchase');
            }

            return $transaction->fresh(['items', 'payments', 'supplier']);
        });
    }

    public function recordPayment(Transaction $transaction, float $amount, string $date, ?string $method = null, ?string $note = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $amount, $date, $method, $note) {
            $transaction->payments()->create([
                'amount' => $amount,
                'date' => $date,
                'method' => $method,
                'note' => $note,
            ]);

            $newPaidAmount = $transaction->paid_amount + $amount;
            $paymentStatus = $newPaidAmount >= $transaction->total_amount ? 3 : ($newPaidAmount > 0 ? 2 : 1);

            $transaction->update(['paid_amount' => $newPaidAmount, 'payment_status' => $paymentStatus]);

            // Auto-post Journal: Debit Accounts Payable, Credit Cash/Bank
            $accountsPayable = Account::where('code', '2100')->firstOrFail();
            $paidFromAccount = Account::where('name', $method === 'bank' ? 'Bank' : 'Cash')->firstOrFail();

            $this->journal->post(
                description: "Payment for {$transaction->reference_no}",
                lines: [
                    ['account_id' => $accountsPayable->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $paidFromAccount->id, 'debit' => 0, 'credit' => $amount],
                ],
                referenceType: 'purchase_payment',
                referenceId: $transaction->id,
                date: $date
            );

            return $transaction->fresh(['payments']);
        });
    }

    // private function generateReferenceNo(): string
    // {
    //     $last = Transaction::where('type', 'purchase')->orderBy('id', 'desc')->first();
    //     $nextNumber = $last ? ((int) substr($last->reference_no, 4)) + 1 : 1;
    //     return 'PUR-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    // }
    private function generateReferenceNo(): string
    {
        $last = Transaction::where('type', 'purchase')
            ->where('reference_no', 'like', 'PUR-%')
            ->orderByRaw('CAST(SUBSTRING(reference_no, 5) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = $last ? ((int) substr($last->reference_no, 4)) + 1 : 1;
        return 'PUR-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
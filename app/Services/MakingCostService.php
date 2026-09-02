<?php

namespace App\Services;

use App\Models\Accounts\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class MakingCostService
{
    public function __construct(private JournalPostingService $journal) {}

    public function createForBatch(int $makingHouseId, int $productionBatchId, float $amount, string $date): Transaction
    {
        return DB::transaction(function () use ($makingHouseId, $productionBatchId, $amount, $date) {
            $transaction = Transaction::create([
                'type' => 'making_cost',
                'reference_no' => $this->generateReferenceNo(),
                'making_house_id' => $makingHouseId,
                'production_batch_id' => $productionBatchId,
                'date' => $date,
                'total_amount' => $amount,
                'paid_amount' => 0,
                'write_off_amount' => 0,
                'payment_status' => 1,
                'status' => 1,
            ]);

            $makingCostExpense = Account::where('code', '5160')->firstOrFail();
            $makingHousePayable = Account::where('code', '2150')->firstOrFail();

            $this->journal->post(
                description: "Making cost — {$transaction->reference_no}",
                lines: [
                    ['account_id' => $makingCostExpense->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $makingHousePayable->id, 'debit' => 0, 'credit' => $amount],
                ],
                referenceType: 'making_cost',
                referenceId: $transaction->id,
                date: $date
            );

            return $transaction;
        });
    }

    /** Same combined payment + write-off pattern as InvoiceService::recordPayment(), just payable-direction. */
    public function recordPayment(Transaction $makingCost, float $amount, string $date, ?string $method, ?string $note, float $writeOffAmount = 0): Transaction
    {
        return DB::transaction(function () use ($makingCost, $amount, $date, $method, $note, $writeOffAmount) {
            if ($amount > 0) {
                $makingCost->payments()->create(['amount' => $amount, 'date' => $date, 'method' => $method, 'note' => $note]);
            }

            $newPaid = $makingCost->paid_amount + $amount;
            $newWriteOff = $makingCost->write_off_amount + $writeOffAmount;
            $settled = $newPaid + $newWriteOff;
            $paymentStatus = $settled >= $makingCost->total_amount ? 3 : ($settled > 0 ? 2 : 1);

            $makingCost->update(['paid_amount' => $newPaid, 'write_off_amount' => $newWriteOff, 'payment_status' => $paymentStatus]);

            $makingHousePayable = Account::where('code', '2150')->firstOrFail();

            if ($amount > 0) {
                $paidFromAccount = Account::where('name', $method === 'bank' ? 'Bank' : 'Cash')->firstOrFail();
                $this->journal->post(
                    description: "Payment for {$makingCost->reference_no}",
                    lines: [
                        ['account_id' => $makingHousePayable->id, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $paidFromAccount->id, 'debit' => 0, 'credit' => $amount],
                    ],
                    referenceType: 'making_cost_payment', referenceId: $makingCost->id, date: $date
                );
            }

            if ($writeOffAmount > 0) {
                $discountReceived = Account::where('code', '4200')->firstOrFail();
                $this->journal->post(
                    description: "Write-off for {$makingCost->reference_no}" . ($note ? " ({$note})" : ''),
                    lines: [
                        ['account_id' => $makingHousePayable->id, 'debit' => $writeOffAmount, 'credit' => 0],
                        ['account_id' => $discountReceived->id, 'debit' => 0, 'credit' => $writeOffAmount],
                    ],
                    referenceType: 'making_cost_write_off', referenceId: $makingCost->id, date: $date
                );
            }

            return $makingCost->fresh(['payments']);
        });
    }

    private function generateReferenceNo(): string
    {
        $last = Transaction::where('type', 'making_cost')->where('reference_no', 'like', 'MKC-%')
            ->orderByRaw('CAST(SUBSTRING(reference_no, 5) AS UNSIGNED) DESC')->first();
        $nextNumber = $last ? ((int) substr($last->reference_no, 4)) + 1 : 1;
        return 'MKC-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
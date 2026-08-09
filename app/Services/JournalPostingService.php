<?php

namespace App\Services;

use App\Models\Accounts\JournalEntry;
use Illuminate\Support\Facades\DB;
use App\Models\CompanySetting;
use Carbon\Carbon;

class JournalPostingService
{
    /**
     * $lines = [ ['account_id' => 1, 'debit' => 500, 'credit' => 0], ['account_id' => 2, 'debit' => 0, 'credit' => 500] ]
     * Debits must equal credits — enforced here so an unbalanced entry never reaches the DB.
     */
    public function post(string $description, array $lines, ?string $referenceType = null, ?int $referenceId = null, ?string $date = null): JournalEntry
    {

    
        
        $totalDebit = array_sum(array_column($lines, 'debit'));
        $totalCredit = array_sum(array_column($lines, 'credit'));

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new \Exception("Journal entry does not balance: debit {$totalDebit} != credit {$totalCredit}");
        }

        return DB::transaction(function () use ($description, $lines, $referenceType, $referenceId, $date) {
            $entry = JournalEntry::create([
                // 'date' => $date ?? now()->toDateString(),
                'date' => $date ?? Carbon::now(CompanySetting::timezone())->toDateString(),
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            return $entry;
        });
    }
}
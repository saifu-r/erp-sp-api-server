<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntryLine;
use Illuminate\Http\Request;

class CashFlowController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $cashBankIds = Account::whereIn('code', ['1100', '1200'])->pluck('id')->toArray();
        $ownerCapitalId = Account::where('code', '3100')->value('id');

        $beginningBalance = $this->cashBalanceAsOf($cashBankIds, $from, true);
        $endingBalance = $this->cashBalanceAsOf($cashBankIds, $to, false);

        $lines = JournalEntryLine::whereIn('journal_entry_lines.account_id', $cashBankIds)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereBetween('journal_entries.date', [$from, $to])
            ->select('journal_entry_lines.*', 'journal_entries.id as entry_id')
            ->with('journalEntry.lines.account')
            ->get();

        $operating = ['Customer Collections' => 0, 'Supplier Payments' => 0, 'Expense Payments' => 0, 'Other Operating' => 0];
        $financing = ['Owner Investment/Withdrawal' => 0];

        foreach ($lines as $line) {
            $net = $line->debit - $line->credit; // positive = cash in, negative = cash out
            $siblingLine = $line->journalEntry->lines->first(fn($l) => $l->id !== $line->id);
            if (!$siblingLine) continue;

            $counterpartAccount = $siblingLine->account;

            if ($counterpartAccount->code === '1300') {
                $operating['Customer Collections'] += $net;
            } elseif ($counterpartAccount->code === '2100') {
                $operating['Supplier Payments'] += $net;
            } elseif ($counterpartAccount->type === 'expense') {
                $operating['Expense Payments'] += $net;
            } elseif ($counterpartAccount->id === $ownerCapitalId) {
                $financing['Owner Investment/Withdrawal'] += $net;
            } else {
                $operating['Other Operating'] += $net;
            }
        }

        // Drop zero-value rows so the report doesn't show empty lines
        $operating = array_filter($operating, fn($v) => $v != 0);
        $financing = array_filter($financing, fn($v) => $v != 0);

        $totalOperating = array_sum($operating);
        $totalFinancing = array_sum($financing);
        $totalInvesting = 0;
        $netChange = $totalOperating + $totalInvesting + $totalFinancing;

        return response()->json([
            'from' => $from, 'to' => $to,
            'beginning_balance' => (float) $beginningBalance,
            'operating' => $operating,
            'total_operating' => (float) $totalOperating,
            'investing' => [],
            'total_investing' => (float) $totalInvesting,
            'financing' => $financing,
            'total_financing' => (float) $totalFinancing,
            'net_change' => (float) $netChange,
            'ending_balance' => (float) $endingBalance,
        ]);
    }

    private function cashBalanceAsOf(array $accountIds, string $date, bool $before): float
    {
        $query = JournalEntryLine::whereIn('journal_entry_lines.account_id', $accountIds)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id');

        $before ? $query->where('journal_entries.date', '<', $date) : $query->where('journal_entries.date', '<=', $date);

        return (float) ($query->selectRaw('SUM(journal_entry_lines.debit) - SUM(journal_entry_lines.credit) as balance')->value('balance') ?? 0);
    }
}
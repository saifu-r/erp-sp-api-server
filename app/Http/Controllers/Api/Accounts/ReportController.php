<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Accounts\Account;
use App\Models\Accounts\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Debits should equal Credits across every account, for a given period (or all-time). */
    public function trialBalance(Request $request)
    {
        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id');

        $this->applyDateRange($query, $request);

        $rows = $query->select('accounts.id', 'accounts.name', 'accounts.code')
            ->selectRaw('SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.name', 'accounts.code')
            ->orderBy('accounts.code')
            ->get();

        $totalDebit = $rows->sum('total_debit');
        $totalCredit = $rows->sum('total_credit');

        return response()->json([
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => round($totalDebit, 2) === round($totalCredit, 2),
        ]);
    }

    /** Income - Expenses = Net Income, for a given period. */
    public function incomeStatement(Request $request)
    {
        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->whereIn('accounts.type', ['income', 'expense']);

        $this->applyDateRange($query, $request);

        $rows = $query->select('accounts.id', 'accounts.name', 'accounts.type')
            ->selectRaw('SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.type')
            ->get();

        $income = $rows->where('type', 'income')->map(fn($r) => [
            'name' => $r->name,
            'amount' => $r->total_credit - $r->total_debit, // income grows on credit
        ]);

        $expense = $rows->where('type', 'expense')->map(fn($r) => [
            'name' => $r->name,
            'amount' => $r->total_debit - $r->total_credit, // expense grows on debit
        ]);

        $totalIncome = $income->sum('amount');
        $totalExpense = $expense->sum('amount');

        return response()->json([
            'income' => $income->values(),
            'expense' => $expense->values(),
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_income' => $totalIncome - $totalExpense,
        ]);
    }

    /** Assets = Liabilities + Equity, as of a given date (cumulative — everything up to that date). */
    public function balanceSheet(Request $request)
    {
        $asOf = $request->query('as_of', now()->toDateString());

        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->whereDate('journal_entries.date', '<=', $asOf);

        $rows = $query->select('accounts.id', 'accounts.name', 'accounts.type')
            ->selectRaw('SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit')
            ->groupBy('accounts.id', 'accounts.name', 'accounts.type')
            ->get();

        $assets = $rows->where('type', 'asset')->map(fn($r) => ['name' => $r->name, 'amount' => $r->total_debit - $r->total_credit]);
        $liabilities = $rows->where('type', 'liability')->map(fn($r) => ['name' => $r->name, 'amount' => $r->total_credit - $r->total_debit]);
        $equity = $rows->where('type', 'equity')->map(fn($r) => ['name' => $r->name, 'amount' => $r->total_credit - $r->total_debit]);

        // Net income to date rolls into Equity as "Retained Earnings" — real accounting practice,
        // since profit/loss isn't a separate bucket, it belongs to the owners once earned.
        $incomeRows = $rows->where('type', 'income');
        $expenseRows = $rows->where('type', 'expense');
        $netIncome = $incomeRows->sum(fn($r) => $r->total_credit - $r->total_debit)
                   - $expenseRows->sum(fn($r) => $r->total_debit - $r->total_credit);

        $totalAssets = $assets->sum('amount');
        $totalLiabilities = $liabilities->sum('amount');
        $totalEquity = $equity->sum('amount') + $netIncome;

        return response()->json([
            'assets' => $assets->values(),
            'liabilities' => $liabilities->values(),
            'equity' => $equity->values(),
            'retained_earnings' => $netIncome,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'is_balanced' => round($totalAssets, 2) === round($totalLiabilities + $totalEquity, 2),
        ]);
    }

    private function applyDateRange($query, Request $request): void
    {
        if ($from = $request->query('from')) {
            $query->whereDate('journal_entries.date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('journal_entries.date', '<=', $to);
        }
    }
}
<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Accounts\JournalEntryLine;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    /** Flat ledger view — one row per debit/credit line, newest first. */
    public function index(Request $request)
    {
        $query = JournalEntryLine::with(['account', 'journalEntry']);

        if ($search = $request->query('search')) {
            $query->whereHas('journalEntry', function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            });
        }

        if ($from = $request->query('from')) {
            $query->whereHas('journalEntry', fn($q) => $q->whereDate('date', '>=', $from));
        }
        if ($to = $request->query('to')) {
            $query->whereHas('journalEntry', fn($q) => $q->whereDate('date', '<=', $to));
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $lines = $query->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->orderBy('journal_entries.date', $orderBy)
            ->orderBy('journal_entry_lines.id', $orderBy)
            ->select('journal_entry_lines.*')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $lines->map(fn($line) => [
            'date' => $line->journalEntry->date,
            'description' => $line->journalEntry->description,
            'account' => $line->account->name,
            'debit' => $line->debit,
            'credit' => $line->credit,
        ]);

        return response()->json(['data' => $data, 'total' => $total]);
    }
}
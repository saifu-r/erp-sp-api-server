<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accounts\JournalEntry;
use App\Services\JournalPostingService;
use Illuminate\Http\Request;

class LedgerAdjustmentController extends Controller
{
    public function __construct(private JournalPostingService $journal) {}

    public function index(Request $request)
    {
        $query = JournalEntry::where('reference_type', 'manual_adjustment')->with('lines.account');

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $data = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'description' => 'required|string|max:255',
            'date' => 'required|date',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
        ]);

        try {
            $entry = $this->journal->post(
                description: $data['description'],
                lines: $data['lines'],
                referenceType: 'manual_adjustment',
                referenceId: null,
                date: $data['date']
            );
            return response()->json($entry->load('lines.account'), 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
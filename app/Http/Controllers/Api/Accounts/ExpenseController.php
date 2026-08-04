<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Accounts\Expense;
use App\Models\Accounts\ExpenseType;
use App\Services\JournalPostingService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private JournalPostingService $journal) {}

    public function index(Request $request)
    {
        $query = Expense::with(['expenseType', 'paidFromAccount']);

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
            'expense_type_id' => 'required|exists:expense_types,id',
            'paid_from_account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'note' => 'nullable|string|max:255',
            'status' => 'required|integer|in:1,2',
        ]);

        $expense = Expense::create($data);
        $expenseType = ExpenseType::findOrFail($data['expense_type_id']);

        // Auto-post: Debit the expense's own account, Credit wherever it was paid from
        $this->journal->post(
            description: "Expense — {$expenseType->name}" . ($data['note'] ? " ({$data['note']})" : ''),
            lines: [
                ['account_id' => $expenseType->account_id, 'debit' => $data['amount'], 'credit' => 0],
                ['account_id' => $data['paid_from_account_id'], 'debit' => 0, 'credit' => $data['amount']],
            ],
            referenceType: 'expense',
            referenceId: $expense->id,
            date: $data['date']
        );

        return response()->json($expense->load(['expenseType', 'paidFromAccount']), 201);
    }

    public function destroy(Expense $expense)
    {
        // Note: does not reverse the journal entry automatically — see note below
        $expense->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function show(Expense $expense)
    {
        return $expense->load(['expenseType', 'paidFromAccount']);
    }
}

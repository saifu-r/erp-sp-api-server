<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Accounts\Account;
use App\Models\Accounts\ExpenseType;
use Illuminate\Http\Request;

class ExpenseTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpenseType::with('account');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $data = $query->orderBy('created_at', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    /** Flat list for the Expense form's dropdown. */
    public function all()
    {
        return ExpenseType::where('status', 1)->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:expense_types,name',
            'status' => 'required|integer|in:1,2',
        ]);

        // Find the "Expenses" parent account to nest the new one under
        $expenseParent = Account::where('code', '5000')->first();

        // Auto-create the matching Chart of Accounts entry, same name
        $account = Account::create([
            'code' => $this->generateNextExpenseCode(),
            'name' => $data['name'],
            'type' => 'expense',
            'parent_id' => $expenseParent?->id,
            'is_system' => false,
        ]);

        $expenseType = ExpenseType::create([
            'name' => $data['name'],
            'account_id' => $account->id,
            'status' => $data['status'],
        ]);

        return response()->json($expenseType->load('account'), 201);
    }

    public function update(Request $request, ExpenseType $expenseType)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:expense_types,name,' . $expenseType->id,
            'status' => 'required|integer|in:1,2',
        ]);

        $expenseType->update(['name' => $data['name'], 'status' => $data['status']]);

        // Keep the linked Account's name in sync, so Journal/reports show the current label
        $expenseType->account()->update(['name' => $data['name']]);

        return $expenseType->load('account');
    }

    public function destroy(ExpenseType $expenseType)
    {
        if ($expenseType->expenses()->exists()) {
            return response()->json(['message' => 'Cannot delete a type that has expenses recorded against it.'], 422);
        }

        $expenseType->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function generateNextExpenseCode(): string
    {
        $last = Account::where('type', 'expense')->orderBy('code', 'desc')->first();
        $next = $last ? ((int) $last->code) + 10 : 5200;
        return (string) $next;
    }
}
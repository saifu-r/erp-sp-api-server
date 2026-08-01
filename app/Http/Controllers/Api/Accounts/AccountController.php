<?php

namespace App\Http\Controllers\Api\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Accounts\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $query = Account::with('parent');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortKey = in_array($request->query('sortKey'), ['code', 'name', 'type']) ? $request->query('sortKey') : 'code';

        $total = $query->count();
        $data = $query->orderBy($sortKey, $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    /** Flat list, no pagination — used by dropdowns (e.g. Expense form's "Paid From" account picker). */
    public function all()
    {
        return Account::orderBy('code')->get();
    }
}
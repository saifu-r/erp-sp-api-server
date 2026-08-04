<?php

namespace App\Http\Controllers\Api\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\PurchaseService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchaseService) {}

    public function index(Request $request)
    {
        $query = Transaction::with(['supplier', 'items.rawMaterial'])->where('type', 'purchase');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($request->query('unpaid_only') === '1') {
            $query->whereIn('payment_status', [1, 2]); // Pending or Partial only
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $data = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function show(Transaction $purchase)
    {
        return $purchase->load(['supplier', 'items.rawMaterial', 'payments']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.raw_material_id' => 'required|exists:raw_materials,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.cost_per_unit' => 'required|numeric|min:0',
            'initial_payment' => 'nullable|numeric|min:0',
        ]);

        $transaction = $this->purchaseService->createPurchase(
            $data['supplier_id'],
            $data['date'],
            $data['items'],
            $data['initial_payment'] ?? null
        );

        return response()->json($transaction, 201);
    }

    public function recordPayment(Request $request, Transaction $purchase)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'method' => 'nullable|string',
            'note' => 'nullable|string|max:255',
        ]);

        if ($data['amount'] > ($purchase->total_amount - $purchase->paid_amount)) {
            return response()->json(['message' => 'Payment exceeds remaining balance.'], 422);
        }

        $transaction = $this->purchaseService->recordPayment(
            $purchase,
            $data['amount'],
            $data['date'],
            $data['method'] ?? null,
            $data['note'] ?? null
        );

        return response()->json($transaction, 201);
    }

    public function payments(Request $request)
    {
        $query = TransactionPayment::with(['transaction.supplier'])
            ->whereHas('transaction', fn($q) => $q->where('type', 'purchase'));

        if ($supplierId = $request->query('supplier_id')) {
            $query->whereHas('transaction', fn($q) => $q->where('supplier_id', $supplierId));
        }

        if ($search = $request->query('search')) {
            $query->whereHas('transaction', function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $payments = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        $data = $payments->map(fn($p) => [
            'id' => $p->id,
            'date' => $p->date,
            'amount' => $p->amount,
            'method' => $p->method,
            'note' => $p->note,
            'reference_no' => $p->transaction->reference_no,
            'supplier_name' => $p->transaction->supplier->name ?? '—',
        ]);

        return response()->json(['data' => $data, 'total' => $total]);
    }
}

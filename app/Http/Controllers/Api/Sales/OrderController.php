<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\SalesOrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private SalesOrderService $orderService) {}

    public function index(Request $request)
    {
        $query = Transaction::with(['customer', 'items.product'])->where('type', 'order');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $data = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function show(Transaction $order)
    {
        return $order->load(['customer', 'items.product', 'payments']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'date' => 'required|date',
            'quotation_id' => 'nullable|exists:transactions,id',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'vat_percent' => 'nullable|numeric|min:0|max:100',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.001',
            'products.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $order = $this->orderService->createOrder(
                $data['customer_id'],
                $data['date'],
                $data['products'],
                $data['discount_percent'] ?? 0,
                $data['vat_percent'] ?? 0,
                $data['quotation_id'] ?? null,
                $request->user()->id
            );
            return response()->json($order, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function markInvoiced(Transaction $order)
    {
        return $this->orderService->markInvoiced($order);
    }
}

<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\QuotationService;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function __construct(private QuotationService $quotationService) {}

    public function index(Request $request)
    {
        $query = Transaction::with('customer')->where('type', 'quotation');

        if ($search = $request->query('search')) {
            $query->where('reference_no', 'like', "%{$search}%");
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $data = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function show(Transaction $quotation)
    {
        return $quotation->load(['items.product', 'customer']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'date' => 'required|date',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'vat_percent' => 'nullable|numeric|min:0|max:100',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.001',
            'products.*.unit_price' => 'required|numeric|min:0',
        ]);

        $quotation = $this->quotationService->createQuotation(
            $data['customer_id'], $data['date'], $data['products'],
            $data['discount_percent'] ?? 0, $data['vat_percent'] ?? 0, $request->user()->id
        );

        return response()->json($quotation, 201);
    }
}
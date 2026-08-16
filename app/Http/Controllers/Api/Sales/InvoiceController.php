<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(Request $request)
    {
        $query = Transaction::with(['customer', 'order'])->where('type', 'invoice');

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

    public function show(Transaction $invoice)
    {
        return $invoice->load(['customer', 'items.product', 'payments', 'order']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'nullable|exists:transactions,id',
            'customer_id' => 'required_without:order_id|exists:customers,id',
            'date' => 'nullable|date',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'vat_percent' => 'nullable|numeric|min:0|max:100',
            'products' => 'required_without:order_id|array|min:1',
            'products.*.product_id' => 'required_with:products|exists:products,id',
            'products.*.quantity' => 'required_with:products|numeric|min:0.001',
            'products.*.unit_price' => 'required_with:products|numeric|min:0',
        ]);

        try {
            $invoice = $this->invoiceService->createInvoice(
                $data['order_id'] ?? null,
                $data['customer_id'] ?? null,
                $data['date'] ?? null,
                $data['products'] ?? null,
                $data['discount_percent'] ?? null,
                $data['vat_percent'] ?? null,
                $request->user()->id
            );
            return response()->json($invoice, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function recordPayment(Request $request, Transaction $invoice)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'method' => 'nullable|string',
            'note' => 'nullable|string|max:255',
        ]);

        if ($data['amount'] > ($invoice->total_amount - $invoice->paid_amount)) {
            return response()->json(['message' => 'Payment exceeds remaining balance.'], 422);
        }

        $invoice = $this->invoiceService->recordPayment($invoice, $data['amount'], $data['date'], $data['method'] ?? null, $data['note'] ?? null);
        return response()->json($invoice, 201);
    }

    public function payments(Request $request)
    {
        $query = TransactionPayment::with(['transaction.customer'])
            ->whereHas('transaction', fn($q) => $q->where('type', 'invoice'));

        if ($customerId = $request->query('customer_id')) {
            $query->whereHas('transaction', fn($q) => $q->where('customer_id', $customerId));
        }

        if ($search = $request->query('search')) {
            $query->whereHas('transaction', function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
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
            'customer_name' => $p->transaction->customer->name ?? '—',
        ]);

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function writeOff(Request $request, Transaction $invoice)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
        ]);

        $remaining = $invoice->total_amount - $invoice->paid_amount - $invoice->write_off_amount;
        if ($data['amount'] > $remaining) {
            return response()->json(['message' => 'Write-off amount exceeds remaining balance.'], 422);
        }

        $invoice = $this->invoiceService->writeOff($invoice, $data['amount'], $data['note'] ?? null, $request->user()->id);
        return response()->json($invoice, 201);
    }
}

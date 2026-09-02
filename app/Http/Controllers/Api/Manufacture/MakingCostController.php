<?php

namespace App\Http\Controllers\Api\Manufacture;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\MakingCostService;
use Illuminate\Http\Request;

class MakingCostController extends Controller
{
    public function __construct(private MakingCostService $service) {}

    public function index(Request $request)
    {
        $query = Transaction::with('makingHouse')->where('type', 'making_cost');
        if ($mhId = $request->query('making_house_id')) $query->where('making_house_id', $mhId);
        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';
        $total = $query->count();
        $data = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();
        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function recordPayment(Request $request, Transaction $makingCost)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'write_off_amount' => 'nullable|numeric|min:0',
            'date' => 'required|date', 'method' => 'nullable|string', 'note' => 'nullable|string|max:255',
        ]);
        $writeOff = $data['write_off_amount'] ?? 0;
        $remaining = $makingCost->total_amount - $makingCost->paid_amount - $makingCost->write_off_amount;
        if (($data['amount'] + $writeOff) > $remaining) {
            return response()->json(['message' => 'Payment + write-off exceeds remaining balance.'], 422);
        }
        $result = $this->service->recordPayment($makingCost, $data['amount'], $data['date'], $data['method'] ?? null, $data['note'] ?? null, $writeOff);
        return response()->json($result, 201);
    }
}
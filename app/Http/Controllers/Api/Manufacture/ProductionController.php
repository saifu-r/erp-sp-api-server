<?php

namespace App\Http\Controllers\Api\Manufacture;

use App\Http\Controllers\Controller;
use App\Models\Manufacture\Production;
use App\Services\ProductionService;
use App\Services\MakingCostService;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function __construct(private ProductionService $productionService, private MakingCostService $makingCostService)
    {
    }

    public function index(Request $request)
    {
        $query = Production::with(['item', 'makingHouse', 'makingCost']);
        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';
        $total = $query->count();
        $data = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();
        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function show(Production $production)
    {
        return $production->load(['item', 'makingHouse', 'materialUsage.rawMaterial', 'batches', 'makingCost.payments']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'making_house_id' => 'required|exists:making_houses,id',
            'materials' => 'required|array|min:1',
            'materials.*.raw_material_id' => 'required|exists:raw_materials,id',
            'materials.*.quantity' => 'required|numeric|min:0.001',
            'rate_per_unit' => 'required|numeric|min:0',
            'estimated_unit' => 'required|numeric|min:0.001',
            'date' => 'required|date',
        ]);

        try {
            $production = $this->productionService->startJob(
                $data['item_id'],
                $data['making_house_id'],
                $data['materials'],
                $data['rate_per_unit'],
                $data['estimated_unit'],
                $data['date']
            );
            return response()->json($production, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function receiveBatch(Request $request, Production $production)
    {
        $data = $request->validate(['quantity' => 'required|numeric|min:0.001', 'date' => 'required|date']);
        try {
            $result = $this->productionService->receiveBatch($production, $data['quantity'], $data['date'], $request->user()->id);
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function finalize(Request $request, Production $production)
    {
        $data = $request->validate([
            'wastage_quantity' => 'nullable|numeric|min:0',
            'wastage_raw_material_id' => 'nullable|exists:raw_materials,id',
        ]);
        try {
            $result = $this->productionService->finalize($production, $data['wastage_quantity'] ?? 0, $data['wastage_raw_material_id'] ?? null);
            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Last-used rate for a given Item + Making House pair — pre-fills the rate field. */
    public function lastRate(Request $request)
    {
        $data = $request->validate(['item_id' => 'required|exists:items,id', 'making_house_id' => 'required|exists:making_houses,id']);
        $last = Production::where('item_id', $data['item_id'])->where('making_house_id', $data['making_house_id'])
            ->orderBy('created_at', 'desc')->first();
        return response()->json(['rate_per_unit' => $last->rate_per_unit ?? null]);
    }

    public function recordPaymentForProduction(Request $request, Production $production)
    {
        $makingCost = $production->makingCost;
        if (!$makingCost) {
            return response()->json(['message' => 'No making cost recorded for this production yet.'], 422);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'write_off_amount' => 'nullable|numeric|min:0',
            'date' => 'required|date',
            'method' => 'nullable|string',
            'note' => 'nullable|string|max:255',
        ]);
        $writeOff = $data['write_off_amount'] ?? 0;
        $remaining = $makingCost->total_amount - $makingCost->paid_amount - $makingCost->write_off_amount;
        if (($data['amount'] + $writeOff) > $remaining) {
            return response()->json(['message' => 'Payment + write-off exceeds remaining balance.'], 422);
        }
        $result = $this->makingCostService->recordPayment($makingCost, $data['amount'], $data['date'], $data['method'] ?? null, $data['note'] ?? null, $writeOff);
        return response()->json($result, 201);
    }
}
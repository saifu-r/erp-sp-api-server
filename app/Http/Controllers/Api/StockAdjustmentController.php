<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(private StockAdjustmentService $service) {}

    public function index(Request $request)
    {
        $query = StockAdjustment::with(['rawMaterial', 'item']);

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
            'adjustable_type' => 'required|in:raw_material,item',
            'raw_material_id' => 'required_if:adjustable_type,raw_material|exists:raw_materials,id',
            'item_id' => 'required_if:adjustable_type,item|exists:items,id',
            'location_type' => 'required_if:adjustable_type,raw_material|in:company,making_house',
            'location_id' => 'required_if:location_type,making_house|nullable|exists:making_houses,id',
            'quantity_change' => 'required|numeric|not_in:0',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        try {
            if ($data['adjustable_type'] === 'raw_material') {
                $adjustment = $this->service->adjustRawMaterial(
                    $data['raw_material_id'],
                    $data['location_type'],
                    $data['location_id'] ?? null,
                    $data['quantity_change'],
                    $data['cost_per_unit'] ?? null,
                    $data['reason'],
                    $data['date'],
                    $request->user()->id
                );
            } else {
                $adjustment = $this->service->adjustItem(
                    $data['item_id'],
                    $data['quantity_change'],
                    $data['cost_per_unit'] ?? null,
                    $data['reason'],
                    $data['date'],
                    $request->user()->id
                );
            }
            return response()->json($adjustment, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(StockAdjustment $stockAdjustment)
    {
        return $stockAdjustment->load(['rawMaterial', 'item']);
    }
}

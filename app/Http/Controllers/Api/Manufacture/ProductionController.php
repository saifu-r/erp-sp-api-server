<?php

namespace App\Http\Controllers\Api\Manufacture;

use App\Http\Controllers\Controller;
use App\Models\Manufacture\Production;
use App\Services\ProductionService;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function __construct(private ProductionService $productionService) {}

    public function index(Request $request)
    {
        $query = Production::with(['item', 'makingHouse', 'wastageRawMaterial']);

        if ($makingHouseId = $request->query('making_house_id')) {
            $query->where('making_house_id', $makingHouseId);
        }

        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 1);
        $orderBy = strtolower($request->query('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';

        $total = $query->count();
        $data = $query->orderBy('date', $orderBy)->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json(['data' => $data, 'total' => $total]);
    }

    public function show(Production $production)
    {
        return $production->load(['item', 'makingHouse', 'materialUsage.rawMaterial', 'wastageRawMaterial']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'making_house_id' => 'required|exists:making_houses,id',
            'materials' => 'required|array|min:1',
            'materials.*.raw_material_id' => 'required|exists:raw_materials,id',
            'materials.*.quantity' => 'required|numeric|min:0.001',
            'quantity_produced' => 'required|numeric|min:0.001',
            'wastage_quantity' => 'nullable|numeric|min:0',
            'wastage_raw_material_id' => 'nullable|exists:raw_materials,id',
            'date' => 'required|date',
        ]);

        try {
            $production = $this->productionService->createProduction(
                $data['item_id'],
                $data['making_house_id'],
                $data['materials'],
                $data['quantity_produced'],
                $data['wastage_quantity'] ?? 0,
                $data['wastage_raw_material_id'] ?? null,
                $data['date']
            );

            return response()->json($production, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
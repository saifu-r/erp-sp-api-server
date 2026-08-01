<?php

namespace App\Http\Controllers\Api\Manufacture;

use App\Http\Controllers\Controller;
use App\Services\RawMaterialStockService;
use Illuminate\Http\Request;

class RawMaterialPurchaseController extends Controller
{
    public function __construct(private RawMaterialStockService $stockService) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'raw_material_id' => 'required|exists:raw_materials,id',
            'quantity' => 'required|numeric|min:0.01',
            'cost_per_unit' => 'required|numeric|min:0',
        ]);

        $batch = $this->stockService->createBatch(
            $data['raw_material_id'],
            'company',
            null,
            $data['quantity'],
            $data['cost_per_unit']
        );

        return response()->json($batch, 201);
    }
}
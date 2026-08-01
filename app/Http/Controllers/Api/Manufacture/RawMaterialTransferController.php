<?php

namespace App\Http\Controllers\Api\Manufacture;

use App\Http\Controllers\Controller;
use App\Services\RawMaterialStockService;
use Illuminate\Http\Request;

class RawMaterialTransferController extends Controller
{
    public function __construct(private RawMaterialStockService $stockService) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'raw_material_id' => 'required|exists:raw_materials,id',
            'making_house_id' => 'required|exists:making_houses,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        // 1. Consume FIFO from company stock
        $consumed = $this->stockService->consumeFifo(
            $data['raw_material_id'],
            'company',
            null,
            $data['quantity']
        );

        // 2. Recreate the same quantity as new batch(es) at the making house,
        //    preserving each source batch's original cost
        $createdBatches = [];
        foreach ($consumed as $portion) {
            $createdBatches[] = $this->stockService->createBatch(
                $data['raw_material_id'],
                'making_house',
                $data['making_house_id'],
                $portion['quantity'],
                $portion['cost_per_unit'],
                $portion['batch_id']
            );
        }

        return response()->json(['transferred_batches' => $createdBatches], 201);
    }
}
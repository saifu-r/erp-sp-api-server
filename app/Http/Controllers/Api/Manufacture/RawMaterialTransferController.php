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
            'source_type' => 'required|in:company,making_house',
            'source_making_house_id' => 'required_if:source_type,making_house|nullable|exists:making_houses,id',
            'destination_making_house_id' => 'required|exists:making_houses,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        if ($data['source_type'] === 'making_house' && $data['source_making_house_id'] == $data['destination_making_house_id']) {
            return response()->json(['message' => 'Source and destination making house cannot be the same.'], 422);
        }

        $sourceLocationType = $data['source_type'];
        $sourceLocationId = $data['source_type'] === 'making_house' ? $data['source_making_house_id'] : null;

        try {
            // 1. Consume FIFO from whichever source was chosen — company or another making house
            $consumed = $this->stockService->consumeFifo(
                $data['raw_material_id'], $sourceLocationType, $sourceLocationId, $data['quantity']
            );

            // 2. Recreate the same quantity at the destination making house, preserving each batch's cost
            $createdBatches = [];
            foreach ($consumed as $portion) {
                $createdBatches[] = $this->stockService->createBatch(
                    $data['raw_material_id'],
                    'making_house',
                    $data['destination_making_house_id'],
                    $portion['quantity'],
                    $portion['cost_per_unit'],
                    $portion['batch_id']
                );
            }

            return response()->json(['transferred_batches' => $createdBatches], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
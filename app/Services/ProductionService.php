<?php

namespace App\Services;

use App\Models\Manufacture\Item;
use App\Models\Manufacture\Production;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    public function __construct(private RawMaterialStockService $stockService) {}

    /**
     * $materials = [ ['raw_material_id' => 1, 'quantity' => 10], ... ] — consumed from the making house's stock
     * $wastageRawMaterialId — usually the shared "Khucha" material's id, nullable if no wastage this run
     */
    public function createProduction(
        int $itemId,
        int $makingHouseId,
        array $materials,
        float $quantityProduced,
        float $wastageQuantity,
        ?int $wastageRawMaterialId,
        string $date
    ): Production {
        return DB::transaction(function () use (
            $itemId, $makingHouseId, $materials, $quantityProduced, $wastageQuantity, $wastageRawMaterialId, $date
        ) {
            $totalCost = 0;
            $usageRecords = [];

            // 1. FIFO-consume each selected raw material from THIS making house's stock
            foreach ($materials as $material) {
                $consumed = $this->stockService->consumeFifo(
                    $material['raw_material_id'], 'making_house', $makingHouseId, $material['quantity']
                );

                foreach ($consumed as $portion) {
                    $totalCost += $portion['quantity'] * $portion['cost_per_unit'];
                    $usageRecords[] = [
                        'raw_material_id' => $material['raw_material_id'],
                        'quantity_consumed' => $portion['quantity'],
                        'cost_per_unit_at_time' => $portion['cost_per_unit'],
                    ];
                }
            }

            // 2. Create the Production record
            $production = Production::create([
                'item_id' => $itemId,
                'making_house_id' => $makingHouseId,
                'quantity_produced' => $quantityProduced,
                'wastage_quantity' => $wastageQuantity,
                'wastage_raw_material_id' => $wastageQuantity > 0 ? $wastageRawMaterialId : null,
                'total_raw_material_cost' => $totalCost,
                'date' => $date,
                'status' => 1,
            ]);

            foreach ($usageRecords as $usage) {
                $production->materialUsage()->create($usage);
            }

            // 3. Update Item stock + weighted-average cost — full raw material cost goes to the Item,
            //    since wastage is zero-cost per the confirmed decision
            $item = Item::findOrFail($itemId);
            $item->addProduction($quantityProduced, $totalCost);

            // 4. Create a zero-cost Khucha batch at this making house, if any wastage occurred
            if ($wastageQuantity > 0 && $wastageRawMaterialId) {
                $this->stockService->createBatch(
                    $wastageRawMaterialId, 'making_house', $makingHouseId, $wastageQuantity, 0
                );
            }

            return $production->fresh(['item', 'makingHouse', 'materialUsage.rawMaterial', 'wastageRawMaterial']);
        });
    }
}
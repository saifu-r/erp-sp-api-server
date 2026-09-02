<?php

namespace App\Services;

use App\Models\Manufacture\Item;
use App\Models\Manufacture\Production;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    public function __construct(
        private RawMaterialStockService $stockService,
        private MakingCostService $makingCostService
    ) {}

    /** Starts a job: consumes raw material once (fixed), sets the estimate. No stock added yet. */
    public function startJob(int $itemId, int $makingHouseId, array $materials, float $ratePerUnit, float $estimatedUnit, string $date): Production
    {
        return DB::transaction(function () use ($itemId, $makingHouseId, $materials, $ratePerUnit, $estimatedUnit, $date) {
            $totalRawMaterialCost = 0;
            $usageRecords = [];

            foreach ($materials as $material) {
                $consumed = $this->stockService->consumeFifo($material['raw_material_id'], 'making_house', $makingHouseId, $material['quantity']);
                foreach ($consumed as $portion) {
                    $totalRawMaterialCost += $portion['quantity'] * $portion['cost_per_unit'];
                    $usageRecords[] = ['raw_material_id' => $material['raw_material_id'], 'quantity_consumed' => $portion['quantity'], 'cost_per_unit_at_time' => $portion['cost_per_unit']];
                }
            }

            $estimatedAvgCost = ($totalRawMaterialCost / $estimatedUnit) + $ratePerUnit;

            $production = Production::create([
                'item_id' => $itemId, 'making_house_id' => $makingHouseId,
                'rate_per_unit' => $ratePerUnit, 'estimated_unit' => $estimatedUnit, 'estimated_avg_cost' => $estimatedAvgCost,
                'total_raw_material_cost' => $totalRawMaterialCost,
                'job_status' => 1, 'date' => $date, 'status' => 1,
            ]);

            foreach ($usageRecords as $usage) {
                $production->materialUsage()->create($usage);
            }

            return $production->fresh(['item', 'makingHouse', 'materialUsage.rawMaterial']);
        });
    }

    /** Receives one batch: adds real stock now (at the estimated rate), creates the making-cost payable for this batch. */
public function receiveBatch(Production $production, float $quantity, string $date, ?int $userId): array
{
    return DB::transaction(function () use ($production, $quantity, $date, $userId) {
        if ($production->job_status === 3) {
            throw new \Exception('This production job is already completed.');
        }

        $batch = $production->batches()->create(['quantity' => $quantity, 'date' => $date, 'user_id' => $userId]);

        /** @var \App\Models\Manufacture\Item $item */
        $item = \App\Models\Manufacture\Item::findOrFail($production->item_id);
        $rawMaterialShare = ($production->total_raw_material_cost / $production->estimated_unit) * $quantity;
        $item->addProduction($quantity, $rawMaterialShare + ($production->rate_per_unit * $quantity));

        $makingCostTx = $this->makingCostService->addBatchCost($production, $quantity, $date);

        if ($production->job_status === 1) {
            $production->update(['job_status' => 2]); // Pending → In Progress on first batch
        }

        return ['batch' => $batch, 'making_cost' => $makingCostTx];
    });
}

    /** Closes the job: computes true cost from actual total, corrects the Item's pooled average, optional wastage. */
    public function finalize(Production $production, float $wastageQuantity = 0, ?int $wastageRawMaterialId = null): array
    {
        return DB::transaction(function () use ($production, $wastageQuantity, $wastageRawMaterialId) {
            if ($production->job_status === 2) {
                throw new \Exception('This production job is already finalized.');
            }

            $actualUnit = (float) $production->batches()->sum('quantity');
            if ($actualUnit <= 0) {
                throw new \Exception('Cannot finalize a job with no batches received.');
            }

            $finalAvgCost = ($production->total_raw_material_cost / $actualUnit) + $production->rate_per_unit;

            // The raw-material portion already added to stock across all batches, vs what SHOULD have been added
            $rawMaterialAlreadyAllocated = ($production->total_raw_material_cost / $production->estimated_unit) * $actualUnit;
            $costVariance = $production->total_raw_material_cost - $rawMaterialAlreadyAllocated;
            // Positive = under-costed so far (need to add more), Negative = over-costed (need to remove)

            /** @var Item $item */
            $item = Item::findOrFail($production->item_id);
            if ($costVariance != 0 && $item->stock_quantity > 0) {
                $currentValue = $item->stock_quantity * $item->avg_cost_per_unit;
                $newValue = $currentValue + $costVariance;
                $item->avg_cost_per_unit = round($newValue / $item->stock_quantity, 4);
                $item->save();
            }

            if ($wastageQuantity > 0 && $wastageRawMaterialId) {
                $this->stockService->createBatch($wastageRawMaterialId, 'making_house', $production->making_house_id, $wastageQuantity, 0);
            }

            $production->update([
                'actual_unit' => $actualUnit,
                'final_avg_cost' => $finalAvgCost,
                'cost_variance' => $costVariance,
                'wastage_quantity' => $wastageQuantity,
                'wastage_raw_material_id' => $wastageQuantity > 0 ? $wastageRawMaterialId : null,
                'job_status' => 2,
            ]);

            return [
                'production' => $production->fresh(),
                'variance_message' => $this->buildVarianceMessage($production->estimated_unit, $actualUnit, $costVariance),
            ];
        });
    }

    private function buildVarianceMessage(float $estimated, float $actual, float $variance): string
    {
        $diff = $actual - $estimated;
        $direction = $diff > 0 ? 'more' : ($diff < 0 ? 'less' : 'exactly as estimated');
        $unitMsg = $diff != 0 ? number_format(abs($diff), 2) . ' units ' . $direction . ' than estimated' : 'Produced exactly as estimated';
        $costMsg = $variance != 0
            ? ('Stock value adjusted by ৳' . number_format(abs($variance), 2) . ' (' . ($variance > 0 ? 'increased' : 'decreased') . ')')
            : 'No cost adjustment needed';
        return "{$unitMsg}. {$costMsg}.";
    }
}
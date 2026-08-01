<?php

namespace App\Services;

use App\Models\Manufacture\RawMaterialBatch;
use Illuminate\Support\Facades\DB;

class RawMaterialStockService
{
    /**
     * Consumes `quantity` of a raw material from a given location, FIFO (oldest batch first).
     * Returns an array of ['batch_id' => int, 'quantity' => float, 'cost_per_unit' => float]
     * describing exactly what was consumed — used for costing and audit logs.
     *
     * @throws \Exception if not enough stock is available
     */
    public function consumeFifo(int $rawMaterialId, string $locationType, ?int $locationId, float $quantity): array
    {
        return DB::transaction(function () use ($rawMaterialId, $locationType, $locationId, $quantity) {
            $batches = RawMaterialBatch::where('raw_material_id', $rawMaterialId)
                ->where('location_type', $locationType)
                ->where('location_id', $locationId)
                ->where('quantity_remaining', '>', 0)
                ->orderBy('created_at', 'asc')
                ->lockForUpdate()
                ->get();

            $available = $batches->sum('quantity_remaining');
            if ($available < $quantity) {
                throw new \Exception("Insufficient stock. Available: {$available}, requested: {$quantity}");
            }

            $remainingToConsume = $quantity;
            $consumed = [];

            foreach ($batches as $batch) {
                if ($remainingToConsume <= 0) break;

                $takeFromThisBatch = min($batch->quantity_remaining, $remainingToConsume);

                $batch->quantity_remaining -= $takeFromThisBatch;
                $batch->save();

                $consumed[] = [
                    'batch_id' => $batch->id,
                    'quantity' => $takeFromThisBatch,
                    'cost_per_unit' => $batch->cost_per_unit,
                ];

                $remainingToConsume -= $takeFromThisBatch;
            }

            return $consumed;
        });
    }

    /** Creates a fresh batch — used for purchases (new stock into the company) and
     *  for transfers (new stock arriving at a making house). */
    public function createBatch(int $rawMaterialId, string $locationType, ?int $locationId, float $quantity, float $costPerUnit, ?int $sourceBatchId = null): RawMaterialBatch
    {
        return RawMaterialBatch::create([
            'raw_material_id' => $rawMaterialId,
            'location_type' => $locationType,
            'location_id' => $locationId,
            'source_batch_id' => $sourceBatchId,
            'quantity_remaining' => $quantity,
            'cost_per_unit' => $costPerUnit,
        ]);
    }
}
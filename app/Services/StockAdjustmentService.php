<?php

namespace App\Services;

use App\Models\Accounts\Account;
use App\Models\Manufacture\Item;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(
        private RawMaterialStockService $stockService,
        private JournalPostingService $journal
    ) {}

    public function adjustRawMaterial(int $rawMaterialId, string $locationType, ?int $locationId, float $quantityChange, ?float $costPerUnit, string $reason, string $date, ?int $userId): StockAdjustment
    {
        return DB::transaction(function () use ($rawMaterialId, $locationType, $locationId, $quantityChange, $costPerUnit, $reason, $date, $userId) {
            $adjustment = StockAdjustment::create([
                'adjustable_type' => 'raw_material',
                'raw_material_id' => $rawMaterialId,
                'location_type' => $locationType,
                'location_id' => $locationId,
                'quantity_change' => $quantityChange,
                'cost_per_unit' => $costPerUnit,
                'reason' => $reason,
                'date' => $date,
                'user_id' => $userId,
            ]);

            $value = 0;

            if ($quantityChange > 0) {
                if (!$costPerUnit) {
                    throw new \Exception('Cost per unit is required for a positive stock adjustment.');
                }
                $this->stockService->createBatch($rawMaterialId, $locationType, $locationId, $quantityChange, $costPerUnit);
                $value = $quantityChange * $costPerUnit;
            } else {
                $consumed = $this->stockService->consumeFifo($rawMaterialId, $locationType, $locationId, abs($quantityChange));
                $value = array_sum(array_map(fn($c) => $c['quantity'] * $c['cost_per_unit'], $consumed));
            }

            $this->postJournal($value, $quantityChange > 0, '1400', $reason, $adjustment->id, $date);

            return $adjustment;
        });
    }

    public function adjustItem(int $itemId, float $quantityChange, ?float $costPerUnit, string $reason, string $date, ?int $userId): StockAdjustment
    {
        return DB::transaction(function () use ($itemId, $quantityChange, $costPerUnit, $reason, $date, $userId) {
            /** @var Item $item */
            $item = Item::findOrFail($itemId);

            $adjustment = StockAdjustment::create([
                'adjustable_type' => 'item',
                'item_id' => $itemId,
                'quantity_change' => $quantityChange,
                'cost_per_unit' => $costPerUnit,
                'reason' => $reason,
                'date' => $date,
                'user_id' => $userId,
            ]);

            $value = 0;

            if ($quantityChange > 0) {
                if (!$costPerUnit) {
                    throw new \Exception('Cost per unit is required for a positive stock adjustment.');
                }
                $value = $quantityChange * $costPerUnit;
                $item->addProduction($quantityChange, $value); // reuses the existing weighted-average logic
            } else {
                if ($item->stock_quantity < abs($quantityChange)) {
                    throw new \Exception('Cannot reduce stock below zero.');
                }
                $value = abs($quantityChange) * $item->avg_cost_per_unit;
                $item->stock_quantity -= abs($quantityChange);
                $item->save();
            }

            $this->postJournal($value, $quantityChange > 0, '1500', $reason, $adjustment->id, $date);

            return $adjustment;
        });
    }

    private function postJournal(float $value, bool $isIncrease, string $inventoryAccountCode, string $reason, int $adjustmentId, string $date): void
    {
        if ($value <= 0) return;

        $inventory = Account::where('code', $inventoryAccountCode)->firstOrFail();
        $openingBalanceEquity = Account::where('code', '3200')->firstOrFail();

        $lines = $isIncrease
            ? [
                ['account_id' => $inventory->id, 'debit' => $value, 'credit' => 0],
                ['account_id' => $openingBalanceEquity->id, 'debit' => 0, 'credit' => $value],
            ]
            : [
                ['account_id' => $openingBalanceEquity->id, 'debit' => $value, 'credit' => 0],
                ['account_id' => $inventory->id, 'debit' => 0, 'credit' => $value],
            ];

        $this->journal->post(
            description: "Stock adjustment: {$reason}",
            lines: $lines,
            referenceType: 'stock_adjustment',
            referenceId: $adjustmentId,
            date: $date
        );
    }
}
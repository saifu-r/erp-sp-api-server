<?php

namespace App\Models\Manufacture;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'unit', 'stock_quantity', 'avg_cost_per_unit', 'status'];
    protected $casts = [
        'unit' => 'integer',
        'stock_quantity' => 'float',
        'avg_cost_per_unit' => 'float',
        'status' => 'integer',
    ];

    public function recipeLines()
    {
        return $this->hasMany(ItemRawMaterial::class);
    }

    /** Adds produced stock and recalculates the weighted-average cost. */
    public function addProduction(float $quantity, float $costForThisBatch): void
    {
        $existingValue = $this->stock_quantity * $this->avg_cost_per_unit;
        $newValue = $existingValue + $costForThisBatch;
        $newQuantity = $this->stock_quantity + $quantity;

        $this->stock_quantity = $newQuantity;
        $this->avg_cost_per_unit = $newQuantity > 0 ? round($newValue / $newQuantity, 2) : 0;
        $this->save();
    }
}
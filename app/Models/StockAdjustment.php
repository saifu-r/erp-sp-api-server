<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'adjustable_type', 'raw_material_id', 'item_id', 'location_type', 'location_id',
        'quantity_change', 'cost_per_unit', 'reason', 'date', 'user_id'
    ];

    protected $casts = ['quantity_change' => 'float', 'cost_per_unit' => 'float'];

    public function rawMaterial() { return $this->belongsTo(\App\Models\Manufacture\RawMaterial::class); }
    public function item() { return $this->belongsTo(\App\Models\Manufacture\Item::class); }
}
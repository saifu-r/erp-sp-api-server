<?php

namespace App\Models\Manufacture;

use Illuminate\Database\Eloquent\Model;

class ProductionMaterialUsage extends Model
{

 protected $table = 'production_material_usage'; // explicit — Eloquent's auto-pluralization guesses "usages"

    protected $fillable = ['production_id', 'raw_material_id', 'quantity_consumed', 'cost_per_unit_at_time'];
    protected $casts = ['quantity_consumed' => 'float', 'cost_per_unit_at_time' => 'float'];

    public function rawMaterial() { return $this->belongsTo(RawMaterial::class); }
}
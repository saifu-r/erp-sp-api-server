<?php

namespace App\Models\Manufacture;

use Illuminate\Database\Eloquent\Model;

class RawMaterialBatch extends Model
{
    protected $fillable = [
        'raw_material_id', 'location_type', 'location_id',
        'source_batch_id', 'quantity_remaining', 'cost_per_unit'
    ];

    protected $casts = [
        'quantity_remaining' => 'float',
        'cost_per_unit' => 'float',
    ];

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
}
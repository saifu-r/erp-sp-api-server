<?php

namespace App\Models\Manufacture;

use Illuminate\Database\Eloquent\Model;

class ItemRawMaterial extends Model
{
    protected $fillable = ['item_id', 'raw_material_id', 'quantity_per_unit'];
    protected $casts = ['quantity_per_unit' => 'float'];

    public function rawMaterial() { return $this->belongsTo(RawMaterial::class); }
}
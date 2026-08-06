<?php

namespace App\Models\Manufacture;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Production extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id', 'making_house_id', 'quantity_produced', 'wastage_quantity',
        'wastage_raw_material_id', 'total_raw_material_cost', 'date', 'status'
    ];

    protected $casts = [
        'quantity_produced' => 'float',
        'wastage_quantity' => 'float',
        'total_raw_material_cost' => 'float',
        'status' => 'integer',
    ];

    public function item() { return $this->belongsTo(Item::class); }
    public function makingHouse() { return $this->belongsTo(MakingHouse::class); }
    public function wastageRawMaterial() { return $this->belongsTo(RawMaterial::class, 'wastage_raw_material_id'); }
    public function materialUsage() { return $this->hasMany(ProductionMaterialUsage::class); }
}
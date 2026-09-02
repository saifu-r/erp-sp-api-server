<?php

namespace App\Models\Manufacture;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Production extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_id', 'making_house_id', 'rate_per_unit', 'estimated_unit', 'estimated_avg_cost',
        'actual_unit', 'final_avg_cost', 'cost_variance', 'job_status',
        'wastage_quantity', 'wastage_raw_material_id', 'date', 'status'
    ];
    protected $casts = [
        'rate_per_unit' => 'float', 'estimated_unit' => 'float', 'estimated_avg_cost' => 'float',
        'actual_unit' => 'float', 'final_avg_cost' => 'float', 'cost_variance' => 'float',
        'job_status' => 'integer', 'wastage_quantity' => 'float', 'status' => 'integer',
    ];

    public function item() { return $this->belongsTo(Item::class); }
    public function makingHouse() { return $this->belongsTo(MakingHouse::class); }
    public function wastageRawMaterial() { return $this->belongsTo(RawMaterial::class, 'wastage_raw_material_id'); }
    public function materialUsage() { return $this->hasMany(ProductionMaterialUsage::class); }
    public function batches() { return $this->hasMany(ProductionBatch::class); }
    public function makingCost() { return $this->hasOne(Transaction::class, 'production_id')->where('type', 'making_cost'); }
}
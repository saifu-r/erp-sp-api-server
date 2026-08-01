<?php

namespace App\Models\Manufacture;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawMaterial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'unit', 'status'];

    protected $casts = ['status' => 'integer'];

    public function batches()
    {
        return $this->hasMany(RawMaterialBatch::class);
    }

    /** Total stock at company level (sum of remaining company-side batches). */
    public function companyStock(): float
    {
        return (float) $this->batches()
            ->where('location_type', 'company')
            ->sum('quantity_remaining');
    }

    /** Total stock at a specific making house. */
    public function makingHouseStock(int $makingHouseId): float
    {
        return (float) $this->batches()
            ->where('location_type', 'making_house')
            ->where('location_id', $makingHouseId)
            ->sum('quantity_remaining');
    }
}
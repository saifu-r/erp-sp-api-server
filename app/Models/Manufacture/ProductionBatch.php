<?php

namespace App\Models\Manufacture;

use Illuminate\Database\Eloquent\Model;

class ProductionBatch extends Model
{
    protected $fillable = ['production_id', 'quantity', 'date', 'user_id'];
    protected $casts = ['quantity' => 'float'];

    public function production() { return $this->belongsTo(Production::class); }
    public function makingCostTransaction() { return $this->hasOne(\App\Models\Transaction::class, 'production_batch_id'); }
}
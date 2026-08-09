<?php

namespace App\Models;

use App\Models\Manufacture\RawMaterial;
use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = ['transaction_id', 'raw_material_id', 'item_id', 'quantity', 'cost_or_price', 'subtotal'];
    protected $casts = ['quantity' => 'float', 'cost_or_price' => 'float', 'subtotal' => 'float'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }
    public function product()
    {
        return $this->belongsTo(\App\Models\Sales\Product::class);
    }
}

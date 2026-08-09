<?php

namespace App\Models\Sales;

use App\Models\Manufacture\Item;
use Illuminate\Database\Eloquent\Model;

class ProductItem extends Model
{
    protected $fillable = ['product_id', 'item_id', 'quantity_required'];
    protected $casts = ['quantity_required' => 'float'];

    public function item() { return $this->belongsTo(Item::class); }
}
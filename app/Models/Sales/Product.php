<?php

namespace App\Models\Sales;

use App\Models\Manufacture\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'sale_price', 'status'];
    protected $casts = ['sale_price' => 'float', 'status' => 'integer'];

    public function productItems() { return $this->hasMany(ProductItem::class); }
}
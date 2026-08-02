<?php

namespace App\Models;

use App\Models\Purchase\Supplier;
use App\Models\Manufacture\MakingHouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'reference_no', 'supplier_id', 'customer_id', 'making_house_id',
        'date', 'total_amount', 'paid_amount', 'payment_status', 'status'
    ];

    protected $casts = [
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'payment_status' => 'integer',
        'status' => 'integer',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function makingHouse() { return $this->belongsTo(MakingHouse::class); }
    public function items() { return $this->hasMany(TransactionItem::class); }
    public function payments() { return $this->hasMany(TransactionPayment::class); }
}
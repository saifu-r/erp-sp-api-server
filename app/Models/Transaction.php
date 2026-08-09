<?php

namespace App\Models;

use App\Models\Purchase\Supplier;
use App\Models\Sales\Customer;
use App\Models\Manufacture\MakingHouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'reference_no',
        'quotation_id',
        'supplier_id',
        'customer_id',
        'user_id',
        'making_house_id',
        'date',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'vat_percent',
        'vat_amount',
        'total_amount',
        'paid_amount',
        'payment_status',
        'order_status',
        'invoice_status',
        'invoice_reference_no',
        'invoiced_at',
        'status'
    ];

    protected $casts = [
        'subtotal' => 'float',
        'discount_percent' => 'float',
        'discount_amount' => 'float',
        'vat_percent' => 'float',
        'vat_amount' => 'float',
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'payment_status' => 'integer',
        'order_status' => 'integer',
        'invoice_status' => 'integer',
        'invoiced_at' => 'datetime',
        'status' => 'integer',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    public function makingHouse()
    {
        return $this->belongsTo(MakingHouse::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }
    public function payments()
    {
        return $this->hasMany(TransactionPayment::class);
    }
    public function quotation()
    {
        return $this->belongsTo(Transaction::class, 'quotation_id');
    }
    public function orders()
    {
        return $this->hasMany(Transaction::class, 'quotation_id');
    } // if a quotation was converted
}

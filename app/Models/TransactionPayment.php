<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionPayment extends Model
{
    protected $fillable = ['transaction_id', 'amount', 'date', 'method', 'note'];
    protected $casts = ['amount' => 'float'];

    public function transaction() { return $this->belongsTo(Transaction::class); }
}
<?php

namespace App\Models\Accounts;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'account_id', 'status'];
    protected $casts = ['status' => 'integer'];

    public function account() { return $this->belongsTo(Account::class); }
}
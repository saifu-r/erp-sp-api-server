<?php

namespace App\Models\Accounts;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['expense_type_id', 'paid_from_account_id', 'amount', 'date', 'note', 'status'];
    protected $casts = ['amount' => 'float', 'status' => 'integer'];

    public function expenseType() { return $this->belongsTo(ExpenseType::class); }
    public function paidFromAccount() { return $this->belongsTo(Account::class, 'paid_from_account_id'); }
}
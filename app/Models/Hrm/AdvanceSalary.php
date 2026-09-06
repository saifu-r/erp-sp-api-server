<?php
namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;

class AdvanceSalary extends Model
{
    protected $fillable = ['employee_id', 'amount', 'remaining_balance', 'date', 'status'];
    protected $casts = ['amount' => 'float', 'remaining_balance' => 'float', 'status' => 'integer'];

    public function employee() { return $this->belongsTo(Employee::class); }
}
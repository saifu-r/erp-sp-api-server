<?php
namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;

class SalaryPayment extends Model
{
    protected $fillable = [
        'employee_id', 'period_month', 'base_salary', 'absent_days', 'absence_deduction',
        'bonus', 'gross_payable', 'advance_recovered', 'net_paid', 'paid_from', 'date', 'user_id'
    ];
    protected $casts = [
        'base_salary' => 'float', 'absent_days' => 'integer', 'absence_deduction' => 'float',
        'bonus' => 'float', 'gross_payable' => 'float', 'advance_recovered' => 'float', 'net_paid' => 'float',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }

    
}
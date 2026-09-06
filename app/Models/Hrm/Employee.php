<?php
namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['name', 'phone', 'address', 'designation', 'joining_date', 'base_salary', 'status'];
    protected $casts = ['base_salary' => 'float', 'status' => 'integer', 'joining_date' => 'date:Y-m-d'];

    public function absences() { return $this->hasMany(Absence::class); }
    public function advanceSalaries() { return $this->hasMany(AdvanceSalary::class); }
    public function salaryPayments() { return $this->hasMany(SalaryPayment::class); }
}
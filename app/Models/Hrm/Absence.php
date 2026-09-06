<?php
namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;

class Absence extends Model
{
    protected $fillable = ['employee_id', 'from_date', 'to_date', 'reason'];
    protected $casts = ['from_date' => 'date', 'to_date' => 'date'];

    public function employee() { return $this->belongsTo(Employee::class); }

    public function getDaysAttribute(): int
    {
        return $this->from_date->diffInDays($this->to_date) + 1;
    }
}
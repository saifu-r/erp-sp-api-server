<?php

namespace App\Models\Manufacture;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MakingHouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'phone', 'address', 'status'];

    protected $casts = [
        'status' => 'integer',
    ];
}
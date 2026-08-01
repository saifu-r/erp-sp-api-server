<?php

namespace App\Models\Accounts;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['code', 'name', 'type', 'parent_id', 'is_system'];
    protected $casts = ['is_system' => 'boolean'];

    public function parent() { return $this->belongsTo(Account::class, 'parent_id'); }
    public function children() { return $this->hasMany(Account::class, 'parent_id'); }
}
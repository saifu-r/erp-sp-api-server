<?php

namespace App\Models\Accounts;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = ['date', 'description', 'reference_type', 'reference_id'];

    public function lines() { return $this->hasMany(JournalEntryLine::class); }
}
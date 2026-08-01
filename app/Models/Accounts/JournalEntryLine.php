<?php

namespace App\Models\Accounts;

use Illuminate\Database\Eloquent\Model;

class JournalEntryLine extends Model
{
    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit'];
    protected $casts = ['debit' => 'float', 'credit' => 'float'];

    public function account() { return $this->belongsTo(Account::class); }

    public function journalEntry() { return $this->belongsTo(JournalEntry::class); }
}
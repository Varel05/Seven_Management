<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntryLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope query to only include lines belonging to active (non-rejected) journal entries.
     */
    public function scopeActive($query)
    {
        return $query->whereHas('journalEntry', function ($q) {
            $q->where('status', '!=', 'rejected');
        });
    }
}

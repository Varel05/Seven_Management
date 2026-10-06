<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $guarded = [];

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Relasi ke baris jurnal aktif (tidak dibatalkan / bukan status rejected).
     */
    public function activeLines()
    {
        return $this->hasMany(JournalEntryLine::class)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', '!=', 'rejected'));
    }
}

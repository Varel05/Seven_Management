<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Scope query to only include active (non-rejected) journal entries.
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'rejected');
    }
}

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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Speaker extends Model
{
    protected $fillable = ['event_id', 'kind', 'name', 'role', 'topic'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

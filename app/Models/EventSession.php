<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSession extends Model
{
    protected $fillable = [
        'event_id', 'position', 'title', 'type', 'start_time',
        'duration_minutes', 'pic', 'note', 'is_ishoma',
    ];

    protected function casts(): array
    {
        return ['is_ishoma' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function endTime(): string
    {
        [$h, $m] = array_map('intval', explode(':', $this->start_time));
        $t = ($h * 60 + $m + $this->duration_minutes) % 1440;

        return sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
    }
}

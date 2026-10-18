<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'user_id', 'name', 'theme', 'location', 'event_date', 'start_time',
        'duration_minutes', 'ishoma_enabled', 'ishoma_minutes', 'features',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'ishoma_enabled' => 'boolean',
            'features' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)->orderBy('position');
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(Speaker::class)->orderBy('id');
    }

    public function scripts(): HasMany
    {
        return $this->hasMany(Script::class);
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->features ?? [], true);
    }

    public function endTime(): string
    {
        [$h, $m] = array_map('intval', explode(':', $this->start_time));
        $t = ($h * 60 + $m + $this->duration_minutes) % 1440;

        return sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
    }

    public function durationLabel(): string
    {
        $h = intdiv($this->duration_minutes, 60);
        $m = $this->duration_minutes % 60;

        return $m ? "{$h} jam {$m} menit" : "{$h} jam";
    }

    /** Susun ulang seluruh rundown dari data acara dan pembicara. */
    public function rebuildRundown(\App\Services\RundownGenerator $generator): void
    {
        $rows = $generator->generate([
            'start_time' => $this->start_time,
            'duration_minutes' => $this->duration_minutes,
            'ishoma_enabled' => $this->ishoma_enabled,
            'ishoma_minutes' => $this->ishoma_minutes,
            'features' => $this->features ?? [],
        ], $this->speakers->map->only(['kind', 'name', 'role', 'topic'])->all());

        $this->sessions()->delete();
        foreach ($rows as $r) {
            $this->sessions()->create([
                'position' => $r['position'], 'title' => $r['title'], 'type' => $r['type'],
                'start_time' => $r['start_time'], 'duration_minutes' => $r['duration_minutes'],
                'pic' => $r['pic'], 'note' => $r['note'], 'is_ishoma' => $r['is_ishoma'],
            ]);
        }
    }

    /** Hitung ulang jam tiap baris sesuai urutan dan durasi (dipakai setelah penyuntingan). */
    public function recalculateTimes(): void
    {
        [$h, $m] = array_map('intval', explode(':', $this->start_time));
        $cursor = $h * 60 + $m;
        foreach ($this->sessions()->get() as $i => $session) {
            $session->update([
                'position' => $i + 1,
                'start_time' => sprintf('%02d:%02d', intdiv($cursor % 1440, 60), $cursor % 60),
            ]);
            $cursor += $session->duration_minutes;
        }
    }
}

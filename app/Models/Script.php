<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Script extends Model
{
    public const TYPES = [
        'sambutan' => 'Sambutan',
        'pidato' => 'Pemateri',
        'mc' => 'Naskah MC',
        'doa' => 'Doa',
    ];

    protected $fillable = ['event_id', 'type', 'content', 'version', 'is_ai'];

    public static function cleanDraftContent(string $content): string
    {
        return str_replace(['*', '#'], '', $content);
    }

    protected function casts(): array
    {
        return ['is_ai' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

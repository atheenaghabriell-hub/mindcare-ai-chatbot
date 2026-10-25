<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'model',
        'status',
        'duration_ms',
        'input_characters',
        'output_characters',
        'context_memory_used',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context_memory_used' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

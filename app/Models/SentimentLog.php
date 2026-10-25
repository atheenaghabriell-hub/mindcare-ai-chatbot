<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentimentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'score',
        'analyzer',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:5',
            'analyzed_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}

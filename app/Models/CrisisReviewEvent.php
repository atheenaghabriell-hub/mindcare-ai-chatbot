<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrisisReviewEvent extends Model
{
    protected $fillable = [
        'user_id',
        'detected_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

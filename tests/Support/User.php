<?php

namespace AmjadIqbal\GridJS\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'joined_at' => 'datetime'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getShoutAttribute(): string
    {
        return strtoupper((string) $this->name);
    }
}

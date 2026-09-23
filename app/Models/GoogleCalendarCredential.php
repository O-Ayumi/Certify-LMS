<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GoogleCalendarCredential extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['user_id', 'google_user_id', 'calendar_id', 'access_token', 'refresh_token', 'expires_at', 'connected_at'];

    protected $casts = ['expires_at' => 'datetime', 'connected_at' => 'datetime'];

    protected $hidden = ['access_token', 'refresh_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

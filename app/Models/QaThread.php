<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['certification_id', 'user_id', 'title', 'body', 'status', 'resolved_at'];

    protected $casts = ['status' => QaThreadStatus::class, 'resolved_at' => 'datetime'];

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class)->oldest();
    }

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || trim($keyword) === '') {
            return $query;
        }
        $like = '%'.$keyword.'%';

        return $query->where(fn (Builder $q) => $q
            ->where('title', 'like', $like)
            ->orWhere('body', 'like', $like)
            ->orWhereHas('replies', fn (Builder $replies) => $replies->where('body', 'like', $like)));
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Student => $query->whereHas('certification', fn (Builder $q) => $q->where('status', CertificationStatus::Published)),
            UserRole::Coach => $query->whereHas('certification', fn (Builder $q) => $q->where('status', CertificationStatus::Published)->assignedTo($user)),
        };
    }

    public function scopeForCertification(Builder $query, ?string $certificationId): Builder
    {
        return $certificationId ? $query->where('certification_id', $certificationId) : $query;
    }

    public function scopeForStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'resolved' => $query->where('status', QaThreadStatus::Resolved),
            'unresolved' => $query->where('status', QaThreadStatus::Open),
            default => $query,
        };
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->latest('created_at');
    }
}

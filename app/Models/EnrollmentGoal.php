<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 受講登録に紐づく受講生個人の学習目標。
 * 達成状態は achieved_at の null / 非 null で表現する。
 */
class EnrollmentGoal extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'description',
        'target_date',
    ];

    protected $casts = [
        'target_date' => 'date',
        'achieved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function isAchieved(): bool
    {
        return $this->achieved_at !== null;
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('achieved_at IS NOT NULL')
            ->orderByRaw('target_date IS NULL')->orderBy('target_date')
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    public function scopeDisplayOrder(Builder $query): Builder
    {
        return $this->scopeOrdered($query);
    }
}

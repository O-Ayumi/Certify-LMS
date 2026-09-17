<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentNoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 受講登録単位で管理する、コーチ・管理者向けの業務メモ。 */
class EnrollmentNote extends Model
{
    /** @use HasFactory<EnrollmentNoteFactory> */
    use HasFactory, HasUlids;

    // author_user_id はリクエストから受け取らず、StoreAction が認証済みUserを設定する。
    protected $fillable = ['body', 'author_user_id'];

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id')->withTrashed();
    }
}

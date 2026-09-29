<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Certification\CertificateAlreadyIssuedException;
use App\Exceptions\Certification\EnrollmentNotPassedException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CertificatePdfService;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * 修了証を発行するユースケース。受講生自己発火型の修了処理 `\App\UseCases\Enrollment\ReceiveCertificateAction` から呼び出される。
 *
 * 業務分岐:
 * - Enrollment が `status=passed` + `passed_at != null` でない: EnrollmentNotPassedException（409）
 * - 同一 Enrollment に対する二重呼出: CertificateAlreadyIssuedException（409、事前 lockForUpdate + exists で検出）
 *
 * 修了証レコードの INSERT は `DB::transaction()` 内で実行する。
 */
final class IssueAction
{
    public function __construct(private readonly CertificatePdfService $pdf) {}

    /**
     * @throws EnrollmentNotPassedException 受講登録が修了状態ではない
     * @throws CertificateAlreadyIssuedException 同一 Enrollment で修了証が既発行
     */
    public function __invoke(Enrollment $enrollment, ?DateTimeInterface $issuedAt = null): Certificate
    {
        if ($enrollment->status !== EnrollmentStatus::Passed || $enrollment->passed_at === null) {
            throw new EnrollmentNotPassedException;
        }

        $path = 'certificates/'.Str::ulid().'.pdf';

        try {
            return DB::transaction(function () use ($enrollment, $issuedAt, $path) {
                // 二重発行ガード: lockForUpdate で同時呼出を直列化し、enrollment_id UNIQUE 違反を事前 SELECT で検出する
                $existing = Certificate::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    throw new CertificateAlreadyIssuedException;
                }

                $enrollment->loadMissing(['user', 'certification']);
                $certificate = new Certificate([
                    'user_id' => $enrollment->user_id,
                    'enrollment_id' => $enrollment->id,
                    'certification_id' => $enrollment->certification_id,
                    'pdf_path' => $path,
                    'issued_at' => $issuedAt ?? now(),
                ]);
                $certificate->setRelation('user', $enrollment->user);
                $certificate->setRelation('certification', $enrollment->certification);

                $this->pdf->store($certificate, $path);
                $certificate->save();

                return $certificate;
            });
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);

            throw $exception;
        }
    }
}

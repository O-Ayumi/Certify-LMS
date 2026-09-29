<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_student_can_download_own_certificate_even_after_graduation(): void
    {
        $certificate = $this->makeCertificate(User::factory()->student()->graduated()->create());
        $this->storePdf($certificate);

        $response = $this->actingAs($certificate->user)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'certificate-'.$certificate->id.'.pdf',
            $response->headers->get('content-disposition'),
        );
    }

    public function test_student_cannot_download_another_students_certificate(): void
    {
        $certificate = $this->makeCertificate();
        $this->storePdf($certificate);
        $otherStudent = User::factory()->student()->inProgress()->create();

        $this->actingAs($otherStudent)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_assigned_coach_can_download_but_unassigned_coach_cannot(): void
    {
        $certificate = $this->makeCertificate();
        $this->storePdf($certificate);
        $admin = User::factory()->admin()->create();
        $assignedCoach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        CertificationCoachAssignment::factory()->create([
            'certification_id' => $certificate->certification_id,
            'user_id' => $assignedCoach->id,
            'assigned_by_user_id' => $admin->id,
        ]);

        $this->actingAs($assignedCoach)
            ->get(route('certificates.download', $certificate))
            ->assertOk();
        $this->actingAs($otherCoach)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_admin_can_download_any_certificate(): void
    {
        $certificate = $this->makeCertificate();
        $this->storePdf($certificate);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('certificates.download', $certificate))
            ->assertOk();
    }

    public function test_download_returns_not_found_when_pdf_file_is_missing(): void
    {
        $certificate = $this->makeCertificate();

        $this->actingAs($certificate->user)
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();
    }

    private function makeCertificate(?User $student = null): Certificate
    {
        $student ??= User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();

        return Certificate::factory()
            ->forEnrollment($enrollment)
            ->create();
    }

    private function storePdf(Certificate $certificate): void
    {
        Storage::disk('private')->put($certificate->pdf_path, '%PDF-1.4 test');
    }
}

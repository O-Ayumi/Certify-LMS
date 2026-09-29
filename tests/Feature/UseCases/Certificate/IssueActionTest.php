<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Certificate;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Certification\CertificateAlreadyIssuedException;
use App\Exceptions\Certification\EnrollmentNotPassedException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\UseCases\Certificate\IssueAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class IssueActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_issues_certificate_for_passed_enrollment(): void
    {
        Storage::fake('private');
        Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $certificate = $action($enrollment);

        $this->assertNotNull($certificate);
        $this->assertSame($enrollment->user_id, $certificate->user_id);
        $this->assertSame($enrollment->id, $certificate->enrollment_id);
        $this->assertSame($enrollment->certification_id, $certificate->certification_id);
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);
        Storage::disk('private')->assertExists($certificate->pdf_path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('private')->get($certificate->pdf_path));
    }

    public function test_throws_when_enrollment_not_passed(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();

        $action = $this->app->make(IssueAction::class);

        $this->expectException(EnrollmentNotPassedException::class);

        $action($enrollment);
    }

    public function test_removes_pdf_when_certificate_record_cannot_be_saved(): void
    {
        Storage::fake('private');
        $enrollment = Enrollment::factory()->passed()->create();
        Certificate::creating(static function (): void {
            throw new RuntimeException('Certificate insert failed');
        });

        try {
            app(IssueAction::class)($enrollment);
            $this->fail('Certificate insert failure should abort issuance');
        } catch (RuntimeException $exception) {
            $this->assertSame('Certificate insert failed', $exception->getMessage());
        }

        $this->assertDatabaseCount('certificates', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_throws_when_passed_status_but_passed_at_is_null(): void
    {
        $enrollment = Enrollment::factory()->create([
            'status' => EnrollmentStatus::Passed->value,
            'passed_at' => null,
        ]);

        $action = $this->app->make(IssueAction::class);

        $this->expectException(EnrollmentNotPassedException::class);

        $action($enrollment);
    }

    public function test_throws_already_issued_on_duplicate_enrollment_call(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $action($enrollment);

        $this->expectException(CertificateAlreadyIssuedException::class);

        $action($enrollment);
    }

    public function test_only_one_certificate_remains_after_duplicate_call_attempt(): void
    {
        Storage::fake('private');

        $enrollment = Enrollment::factory()->passed()->create();

        $action = $this->app->make(IssueAction::class);
        $action($enrollment);

        try {
            $action($enrollment);
        } catch (CertificateAlreadyIssuedException $e) {
            // expected
        }

        $this->assertSame(1, Certificate::query()->where('enrollment_id', $enrollment->id)->count());
    }
}

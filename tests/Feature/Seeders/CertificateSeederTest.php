<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use Database\Seeders\CertificateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_real_certificates_for_different_single_assigned_coaches(): void
    {
        Storage::fake('private');
        $admin = User::factory()->admin()->create();
        $coachOne = User::factory()->coach()->create();
        $coachTwo = User::factory()->coach()->create();

        $certifications = collect([$coachOne, $coachTwo])->map(function (User $coach) use ($admin) {
            $certification = Certification::factory()->published()->create();
            CertificationCoachAssignment::factory()->create([
                'certification_id' => $certification->id,
                'user_id' => $coach->id,
                'assigned_by_user_id' => $admin->id,
            ]);

            return $certification;
        });

        $fixedStudent = User::factory()->student()->graduated()->create([
            'name' => '卒業生花子',
            'email' => 'student-graduated@certify-lms.test',
        ]);
        User::factory()->student()->graduated()->create();

        app(CertificateSeeder::class)->run();

        $certificates = Certificate::query()->with('certification.coaches')->get();

        $this->assertCount(2, $certificates);
        $this->assertSame($fixedStudent->id, $certificates[0]->user_id);
        $this->assertEqualsCanonicalizing(
            $certifications->pluck('id')->all(),
            $certificates->pluck('certification_id')->all(),
        );
        $this->assertNotSame(
            $certificates[0]->certification->coaches->sole()->id,
            $certificates[1]->certification->coaches->sole()->id,
        );

        foreach ($certificates as $certificate) {
            Storage::disk('private')->assertExists($certificate->pdf_path);
        }

        $certificateIds = $certificates->modelKeys();
        $statusLogCount = EnrollmentStatusLog::query()->count();
        $pdfFileCount = count(Storage::disk('private')->allFiles());

        app(CertificateSeeder::class)->run();

        $this->assertSame($certificateIds, Certificate::query()->orderBy('id')->pluck('id')->all());
        $this->assertSame($statusLogCount, EnrollmentStatusLog::query()->count());
        $this->assertSame($pdfFileCount, count(Storage::disk('private')->allFiles()));
    }
}

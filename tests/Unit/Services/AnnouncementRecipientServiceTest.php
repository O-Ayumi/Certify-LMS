<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\AnnouncementRecipientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementRecipientServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_students_returns_only_active_students(): void
    {
        $active = User::factory()->create();
        User::factory()->graduated()->create();
        User::factory()->coach()->create();

        $recipients = (new AnnouncementRecipientService)->resolve([
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $this->assertSame([$active->id], $recipients->modelKeys());
    }

    public function test_certification_target_requires_learning_enrollment(): void
    {
        $certification = Certification::factory()->published()->create();
        $included = User::factory()->create();
        $excluded = User::factory()->create();

        Enrollment::factory()->learning()->create([
            'user_id' => $included->id,
            'certification_id' => $certification->id,
        ]);
        Enrollment::factory()->state(['status' => EnrollmentStatus::Passed->value])->create([
            'user_id' => $excluded->id,
            'certification_id' => $certification->id,
        ]);

        $recipients = (new AnnouncementRecipientService)->resolve([
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ]);

        $this->assertSame([$included->id], $recipients->modelKeys());
    }

    public function test_user_target_requires_an_active_student(): void
    {
        $student = User::factory()->create();

        $recipients = (new AnnouncementRecipientService)->resolve([
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $student->id,
        ]);

        $this->assertSame([$student->id], $recipients->modelKeys());
    }
}

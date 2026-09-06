<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Database\Seeders\CertificationCategorySeeder;
use Database\Seeders\CertificationSeeder;
use Database\Seeders\QaThreadSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaThreadSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_pagination_and_moderation_demo_data(): void
    {
        $this->seed([
            UserSeeder::class,
            CertificationCategorySeeder::class,
            CertificationSeeder::class,
            QaThreadSeeder::class,
        ]);

        $student = User::query()->where('email', 'student@certify-lms.test')->firstOrFail();
        $published = Certification::query()->where('status', CertificationStatus::Published)->get();

        $this->assertCount(5, $published);
        $this->assertSame(25, QaThread::query()->whereIn('certification_id', $published->modelKeys())->count());
        $this->assertSame(
            25,
            QaThread::query()->whereIn('certification_id', $published->modelKeys())->where('user_id', $student->id)->count(),
        );

        foreach ($published as $certification) {
            $replyCounts = QaThread::query()
                ->where('certification_id', $certification->id)
                ->oldest('created_at')
                ->withCount('replies')
                ->get()
                ->pluck('replies_count')
                ->sort()
                ->values()
                ->all();

            $this->assertSame([0, 0, 1, 2, 3], $replyCounts);
        }

        $this->assertSame(
            2,
            QaThread::query()->whereHas(
                'certification',
                fn ($query) => $query->whereIn('status', [CertificationStatus::Draft, CertificationStatus::Archived]),
            )->count(),
        );
        $this->assertTrue(QaThread::query()->whereHas('replies', fn ($query) => $query->where('body', 'like', '%回答本文検索確認%'))->exists());
    }
}

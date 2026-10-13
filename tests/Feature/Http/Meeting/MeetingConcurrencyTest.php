<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MeetingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_parallel_booking_requests_allow_one_success_and_return_409_for_the_other(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the concurrency test.');
        }

        $coach = User::factory()->coach()->create();
        $students = User::factory()->count(2)->student()->inProgress()->create([
            'max_meetings' => 3,
        ]);
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $enrollments = $students->map(
            fn (User $student) => Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create()
        );
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);
        CoachAvailability::factory()->forCoach($coach)->onDay($scheduledAt->dayOfWeek)->timeRange('09:00:00', '18:00:00')->create();
        $directory = sys_get_temp_dir().'/meeting-concurrency-'.Str::random(16);
        mkdir($directory);
        $ready = $directory.'/ready';
        $results = $directory.'/results';
        mkdir($ready);
        mkdir($results);

        $children = [];
        foreach ($enrollments as $index => $enrollment) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid);

            if ($pid === 0) {
                try {
                    file_put_contents($ready.'/'.$index, 'ready');
                    while (count(glob($ready.'/*') ?: []) < 2) {
                        usleep(1000);
                    }

                    DB::purge();
                    DB::reconnect();
                    Auth::loginUsingId($students[$index]->id);
                    $request = Request::create(route('meetings.store', $enrollment), 'POST', [
                        'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                        'topic' => 'parallel booking',
                    ], [], [], ['HTTP_ACCEPT' => 'application/json']);
                    $response = app(Kernel::class)->handle($request);
                    file_put_contents($results.'/'.$index, $response->getStatusCode().'|'.$response->getContent());
                } catch (\Throwable $exception) {
                    file_put_contents($results.'/'.$index, $exception::class);
                }

                exit(0);
            }

            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $outcomes = array_map(
            fn (string $path): string => trim((string) file_get_contents($path)),
            glob($results.'/*') ?: [],
        );

        if (count(array_filter($outcomes, fn (string $outcome): bool => str_starts_with($outcome, '302|'))) !== 1
            || count(array_filter($outcomes, fn (string $outcome): bool => str_starts_with($outcome, '409|'))) !== 1) {
            throw new \RuntimeException('Parallel booking outcomes: '.implode(' || ', $outcomes));
        }

        $this->assertCount(2, $outcomes);
        $this->assertCount(1, array_filter($outcomes, fn (string $outcome): bool => str_starts_with($outcome, '302|')), implode(', ', $outcomes));
        $this->assertCount(1, array_filter($outcomes, fn (string $outcome): bool => str_starts_with($outcome, '409|')), implode(', ', $outcomes));
        DB::reconnect();
        $this->assertSame(1, Meeting::query()->where('scheduled_at', $scheduledAt)->count());
        $this->assertSame(
            1,
            MeetingQuotaTransaction::query()
                ->whereIn('user_id', $students->pluck('id'))
                ->where('type', MeetingQuotaTransactionType::Consumed->value)
                ->count(),
        );
    }
}

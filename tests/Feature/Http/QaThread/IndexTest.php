<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_only_threads_for_published_certifications(): void
    {
        $student = User::factory()->student()->create();
        $published = Certification::factory()->published()->create();
        $archived = Certification::factory()->archived()->create();
        $visible = QaThread::factory()->for($published)->create(['title' => '表示対象']);
        $hidden = QaThread::factory()->for($archived)->create(['title' => '非表示対象']);

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertOk();
        $this->assertSame([$visible->id], $response->viewData('threads')->getCollection()->pluck('id')->all());
        $response->assertSee('表示対象')->assertDontSee('非表示対象');
        unset($hidden);
    }

    public function test_admin_can_view_threads_for_unpublished_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->for(Certification::factory()->archived())->create(['title' => '管理者表示']);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk()->assertSee('管理者表示');
        $this->assertSame([$thread->id], $response->viewData('threads')->getCollection()->pluck('id')->all());
    }

    public function test_coach_sees_only_threads_for_assigned_published_certifications(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assigned = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $assigned->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $visible = QaThread::factory()->for($assigned)->create(['title' => '担当資格']);
        $hidden = QaThread::factory()->for($other)->create(['title' => '担当外資格']);

        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        $response->assertOk()->assertSee('担当資格')->assertDontSee('担当外資格');
        $this->assertSame([$visible->id], $response->viewData('threads')->getCollection()->pluck('id')->all());
        unset($hidden);
    }

    public function test_filters_and_query_string_are_applied(): void
    {
        $student = User::factory()->student()->create();
        $first = Certification::factory()->published()->create();
        $second = Certification::factory()->published()->create();
        $match = QaThread::factory()->for($first)->resolved()->create(['title' => '検索対象']);
        QaThread::factory()->for($first)->create(['title' => '状態違い']);
        QaThread::factory()->for($second)->resolved()->create(['title' => '資格違い']);

        $response = $this->actingAs($student)->get(route('qa-board.index', [
            'certification_id' => $first->id,
            'status' => 'resolved',
            'keyword' => '検索',
            'page' => 1,
        ]));

        $response->assertOk()->assertSee('検索対象')->assertDontSee('状態違い')->assertDontSee('資格違い');
        $this->assertSame([$match->id], $response->viewData('threads')->getCollection()->pluck('id')->all());
        $this->assertSame(1, $response->viewData('threads')->currentPage());
    }

    public function test_threads_are_newest_first_and_paginated_by_20(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        QaThread::factory()->for($certification)->count(21)->create();

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $threads = $response->viewData('threads');
        $response->assertOk();
        $this->assertSame(20, $threads->perPage());
        $this->assertSame(21, $threads->total());
        $this->assertTrue($threads->getCollection()->first()->created_at->gte($threads->getCollection()->last()->created_at));
    }

    public function test_invalid_filters_return_unprocessable_entity(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->getJson(route('qa-board.index', ['status' => 'invalid']))
            ->assertUnprocessable();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('qa-board.index'))->assertRedirect(route('login'));
    }

    public function test_inactive_users_cannot_view_public_index(): void
    {
        foreach ([User::factory()->graduated(), User::factory()->invited(), User::factory()->withdrawn()] as $factory) {
            $this->actingAs($factory->create())
                ->get(route('qa-board.index'))
                ->assertForbidden();
        }
    }
}

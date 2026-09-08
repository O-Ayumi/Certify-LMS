<?php

declare(strict_types=1);

namespace Tests\Feature\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 購入モデルは作らず、後続チケットに必要なRESTRICT外部キーの契約を実DBで検証する。
 * DDLは暗黙コミットを伴うためRefreshDatabaseのトランザクションとは分離する。
 */
class MeetingPackDeletionReferenceTest extends TestCase
{
    use DatabaseMigrations;

    public function test_referenced_draft_and_archived_packs_cannot_be_deleted(): void
    {
        Schema::create('meeting_pack_reference_test', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('meeting_pack_id')->constrained('meeting_packs')->restrictOnDelete();
        });

        try {
            $this->actingAs(User::factory()->admin()->create());

            foreach (['draft', 'archived'] as $status) {
                $plan = MeetingPack::factory()->create(['status' => $status]);
                DB::table('meeting_pack_reference_test')->insert(['meeting_pack_id' => $plan->id]);

                $this->deleteJson(route('admin.meeting-packs.destroy', $plan))
                    ->assertConflict()->assertJsonPath('message', '購入履歴などの関連データがあるため、この面談パックは削除できません。');
                $this->assertModelExists($plan);
                $this->assertDatabaseHas('meeting_pack_reference_test', ['meeting_pack_id' => $plan->id]);

                $url = route('admin.meeting-packs.show', $plan);
                $this->from($url)->delete(route('admin.meeting-packs.destroy', $plan))
                    ->assertRedirect($url)->assertSessionHas('error');
                $this->assertModelExists($plan);
            }
        } finally {
            Schema::dropIfExists('meeting_pack_reference_test');
        }
    }
}

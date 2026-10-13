<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            // coach_id の外部キーが利用できる単独インデックスを先に用意する。
            $table->index('coach_id', 'meetings_coach_id_index');
            $table->dropUnique('meetings_coach_scheduled_at_unique');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unique(['coach_id', 'scheduled_at'], 'meetings_coach_scheduled_at_unique');
            // Keep the standalone coach_id index until the earlier migration
            // rolls back its composite index. MySQL may require an index on
            // the FK column while that rollback is performed.
        });
    }
};

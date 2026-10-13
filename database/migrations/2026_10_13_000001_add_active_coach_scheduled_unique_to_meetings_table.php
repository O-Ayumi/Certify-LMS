<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL does not support partial unique indexes. A nullable generated
        // column gives canceled meetings no uniqueness key while reserving the
        // coach/time pair for reserved and completed meetings.
        DB::statement(<<<'SQL'
            ALTER TABLE meetings
            ADD COLUMN active_coach_scheduled_key VARCHAR(280)
            GENERATED ALWAYS AS (
                CASE
                    WHEN status IN ('reserved', 'completed')
                    THEN CONCAT(coach_id, ':', DATE_FORMAT(scheduled_at, '%Y-%m-%d %H:%i:%s'))
                    ELSE NULL
                END
            ) STORED,
            ADD UNIQUE INDEX meetings_active_coach_scheduled_unique (active_coach_scheduled_key)
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE meetings
            DROP INDEX meetings_active_coach_scheduled_unique,
            DROP COLUMN active_coach_scheduled_key
        SQL);
    }
};

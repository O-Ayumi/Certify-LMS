<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', fn (Blueprint $table) => $table->string('google_event_id')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn('google_event_id'));
    }
};

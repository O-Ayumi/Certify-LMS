<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_conversations', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $t->foreignUlid('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignUlid('section_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title', 100)->default('新しい相談');
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['user_id', 'section_id']);
        });
        Schema::create('ai_chat_messages', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('conversation_id')->constrained('ai_chat_conversations')->cascadeOnDelete();
            $t->string('role', 20);
            $t->text('content');
            $t->string('status', 20)->default('completed');
            $t->unsignedInteger('response_time_ms')->nullable();
            $t->string('model')->nullable();
            $t->timestamps();
            $t->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_conversations');
    }
};

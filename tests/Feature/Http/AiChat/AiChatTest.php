<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);
    }

    public function test_student_can_create_a_conversation(): void
    {
        $user = $this->student();

        $response = $this->actingAs($user)->post('/ai-chat/conversations', [], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()->assertJsonPath('conversation.user_id', $user->id);
        $this->assertDatabaseHas('ai_chat_conversations', ['user_id' => $user->id]);
    }

    public function test_student_can_send_a_message_and_save_the_ai_response(): void
    {
        config(['ai-chat.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '学習の回答です。']]]]],
        ])]);

        $user = $this->student();
        $conversation = AiChatConversation::create(['user_id' => $user->id, 'title' => '新しい相談']);

        $response = $this->actingAs($user)->postJson(
            "/ai-chat/conversations/{$conversation->id}/messages",
            ['content' => '質問です'],
        );

        $response->assertOk()->assertJsonPath('assistant_message.content', '学習の回答です。');
        $this->assertDatabaseHas('ai_chat_messages', ['conversation_id' => $conversation->id, 'role' => 'user', 'content' => '質問です']);
        $this->assertDatabaseHas('ai_chat_messages', ['conversation_id' => $conversation->id, 'role' => 'assistant', 'status' => 'completed']);
    }

    public function test_another_student_cannot_access_a_conversation(): void
    {
        $owner = $this->student();
        $other = $this->student();
        $conversation = AiChatConversation::create(['user_id' => $owner->id, 'title' => '秘密の相談']);

        $this->actingAs($other)->get("/ai-chat/conversations/{$conversation->id}")->assertForbidden();
        $this->actingAs($other)->postJson("/ai-chat/conversations/{$conversation->id}/messages", ['content' => '不正アクセス'])->assertForbidden();
    }

    public function test_daily_limit_is_applied_per_student_message(): void
    {
        config(['ai-chat.daily_limit' => 1, 'ai-chat.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '回答']]]]],
        ])]);
        $user = $this->student();
        $conversation = AiChatConversation::create(['user_id' => $user->id, 'title' => '相談']);

        $this->actingAs($user)->postJson("/ai-chat/conversations/{$conversation->id}/messages", ['content' => '一回目'])->assertOk();
        $this->actingAs($user)->postJson("/ai-chat/conversations/{$conversation->id}/messages", ['content' => '二回目'])->assertStatus(429);
    }

    public function test_gemini_failure_keeps_the_question_and_returns_safe_error(): void
    {
        config(['ai-chat.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'secret api detail']], 503)]);
        $user = $this->student();
        $conversation = AiChatConversation::create(['user_id' => $user->id, 'title' => '相談']);

        $response = $this->actingAs($user)->postJson("/ai-chat/conversations/{$conversation->id}/messages", ['content' => '再質問したい']);

        $response->assertStatus(502)->assertJsonMissing(['secret api detail'])->assertJsonPath('upstream_status', 503);
        $this->assertDatabaseHas('ai_chat_messages', ['conversation_id' => $conversation->id, 'role' => 'user', 'content' => '再質問したい']);
        $this->assertDatabaseMissing('ai_chat_messages', ['conversation_id' => $conversation->id, 'role' => 'assistant', 'status' => 'error']);
        $this->assertNotNull($conversation->fresh()->last_message_at);
    }

    public function test_message_requires_content(): void
    {
        $user = $this->student();
        $conversation = AiChatConversation::create(['user_id' => $user->id, 'title' => '相談']);

        $this->actingAs($user)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", ['content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
    }

    public function test_missing_conversation_returns_not_found(): void
    {
        $this->actingAs($this->student())
            ->get('/ai-chat/conversations/01h00000000000000000000000')
            ->assertNotFound();
    }

    public function test_owner_can_update_and_delete_a_conversation(): void
    {
        $user = $this->student();
        $conversation = AiChatConversation::create(['user_id' => $user->id, 'title' => '相談']);

        $this->actingAs($user)
            ->patch("/ai-chat/conversations/{$conversation->id}", [
                'title' => '更新後の相談',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '更新後の相談',
        ]);

        $this->actingAs($user)
            ->delete("/ai-chat/conversations/{$conversation->id}")
            ->assertRedirect();
        $this->assertSoftDeleted('ai_chat_conversations', ['id' => $conversation->id]);
    }

    public function test_ai_chat_can_be_disabled(): void
    {
        config(['ai-chat.enabled' => false]);

        $this->actingAs($this->student())
            ->get('/ai-chat')
            ->assertNotFound();
    }

    public function test_empty_gemini_response_is_saved_as_error(): void
    {
        config(['ai-chat.gemini.api_key' => 'test-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => []]]],
        ])]);

        $user = $this->student();
        $conversation = AiChatConversation::create(['user_id' => $user->id, 'title' => '相談']);

        $this->actingAs($user)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", ['content' => '空回答の確認'])
            ->assertStatus(502)
            ->assertJsonPath('upstream_status', 502);
        $this->assertDatabaseMissing('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'status' => 'error',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Section;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiChatController extends Controller
{
    private function enabled(): void
    {
        if (! config('ai-chat.enabled', true)) {
            abort(404);
        }
    }

    public function index(Request $r)
    {
        $this->enabled();
        $c = $r->user()->aiChatConversations()->latest('last_message_at')->first();

        return $c ? redirect()->route('ai-chat.conversations.show', $c) : view('ai-chat.empty-state');
    }

    public function show(AiChatConversation $conversation)
    {
        $this->enabled();
        $this->authorize('view', $conversation);
        $conversation->load('messages', 'enrollment.certification', 'section');

        return request()->expectsJson() ? response()->json(['conversation' => $conversation, 'messages' => $conversation->messages]) : view('ai-chat.show', ['conversation' => $conversation]);
    }

    public function store(StoreConversationRequest $req)
    {
        $this->enabled();
        $u = $req->user();
        $section = $req->section_id ? Section::with('chapter.part.certification')->findOrFail($req->section_id) : null;
        $enrollment = $req->enrollment_id ? $u->enrollments()->find($req->enrollment_id) : null;
        if ($section) {
            $certificationId = $section->chapter?->part?->certification_id;
            if (! $certificationId) {
                abort(422, 'Invalid section');
            } if ($enrollment && ! $enrollment->certification()->whereKey($certificationId)->exists()) {
                abort(422, 'Enrollment does not match section');
            } $enrollment = $enrollment ?: $u->enrollments()->whereHas('certification', fn ($q) => $q->whereKey($certificationId))->first();
            $existing = $u->aiChatConversations()->where('section_id', $section->id)->first();
            if ($existing && ! $req->filled('content')) {
                return response()->json(['conversation' => $existing]);
            }
        } $c = $u->aiChatConversations()->create(['title' => '新しい相談', 'section_id' => $section?->id, 'enrollment_id' => $enrollment?->id]);
        if ($req->filled('content')) {
            $this->send($req->content, $c);
        }

        return $req->expectsJson() ? response()->json(['conversation' => $c->fresh('messages')]) : redirect()->route('ai-chat.conversations.show', $c);
    }

    public function update(UpdateConversationRequest $r, AiChatConversation $conversation)
    {
        $this->enabled();
        $this->authorize('update', $conversation);
        $conversation->update(['title' => $r->title]);

        return back();
    }

    public function destroy(AiChatConversation $conversation)
    {
        $this->enabled();
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return redirect()->route('ai-chat.index');
    }

    public function message(StoreMessageRequest $r, AiChatConversation $conversation)
    {
        $this->enabled();
        $this->authorize('view', $conversation);

        return response()->json($this->send($r->content, $conversation));
    }

    private function send(string $content, AiChatConversation $conversation): array
    {
        $u = auth()->user();
        $today = AiChatMessage::whereHas('conversation', fn ($q) => $q->where('user_id', $u->id))->where('role', 'user')->whereDate('created_at', today())->count();
        if ($today >= config('ai-chat.daily_limit', 20)) {
            abort(429);
        }
        $user = $conversation->messages()->create(['role' => 'user', 'content' => $content, 'status' => 'completed']);
        $history = $conversation->messages()->latest()->limit(config('ai-chat.max_history', 20))->get()->reverse();
        $context = ['certification' => ($conversation->enrollment ?? (auth()->user()->defaultEnrollment?->status === EnrollmentStatus::Learning ? auth()->user()->defaultEnrollment : null))?->certification?->name, 'section' => $conversation->section?->title];
        try {
            $a = app(GeminiService::class)->answer($history->map(fn ($m) => ['role' => $m->role->value, 'content' => $m->content])->all(), $context);
            $assistant = $conversation->messages()->create(['role' => 'assistant', 'content' => $a['content'], 'status' => 'completed', 'response_time_ms' => $a['elapsed_ms'], 'model' => config('ai-chat.gemini.model')]);
            if (config('ai-chat.title_generation_enabled', true) && $conversation->title === '新しい相談') {
                $conversation->update(['title' => Str::limit($content, 100)]);
            }
        } catch (\Throwable $e) {
            abort(response()->json(['message' => 'AI unavailable', 'upstream_status' => $e->getCode() ?: 502], 502));
        }
        $conversation->update(['last_message_at' => now()]);

        return ['user_message' => $user, 'assistant_message' => $assistant, 'conversation' => $conversation->fresh()];
    }
}

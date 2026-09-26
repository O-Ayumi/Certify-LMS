<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'student@example.com')->first()
            ?: User::where('role', 'student')->first();
        if (! $user) {
            return;
        }

        $conversation = AiChatConversation::firstOrCreate(
            ['user_id' => $user->id, 'title' => 'アルゴリズムの復習'],
            [
                'enrollment_id' => $user->enrollments()->first()?->id,
                'last_message_at' => now()->subDay(),
            ],
        );

        if ($conversation->messages()->doesntExist()) {
            $conversation->messages()->createMany([
                ['role' => 'user', 'content' => 'この分野の要点を教えてください。', 'status' => 'completed'],
                ['role' => 'assistant', 'content' => 'まずは基本用語と典型問題を確認しましょう。', 'status' => 'completed'],
            ]);
        }
    }
}

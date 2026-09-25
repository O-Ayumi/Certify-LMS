<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AiChatMessage extends Model
{
    use HasUlids;

    protected $fillable = ['conversation_id', 'role', 'content', 'status', 'response_time_ms', 'model'];

    protected $casts = [
        'role' => AiChatMessageRole::class,
        'status' => AiChatMessageStatus::class,
        'response_time_ms' => 'integer',
    ];

    public function conversation()
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiChatConversation extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['user_id', 'enrollment_id', 'section_id', 'title', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function messages()
    {
        return $this->hasMany(AiChatMessage::class, 'conversation_id')->orderBy('created_at');
    }
}

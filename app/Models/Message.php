<?php

namespace App\Models;

use App\Enums\MessageSenderType;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_user_id',
        'external_message_id',
        'body',
        'message_type',
        'status',
        'failed_at',
        'failure_reason',
        'metadata',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sender_type' => MessageSenderType::class,
            'message_type' => MessageType::class,
            'status' => MessageStatus::class,
            'failed_at' => 'datetime',
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}

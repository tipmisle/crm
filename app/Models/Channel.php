<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'type',
        'display_name',
        'handle',
        'status',
        'connected_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'connected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}

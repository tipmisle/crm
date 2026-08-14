<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'integration_id',
        'type',
        'external_account_id',
        'display_name',
        'handle',
        'status',
        'connected_at',
        'last_synced_at',
        'metadata',
        'access_token',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
            'access_token' => 'encrypted',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
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

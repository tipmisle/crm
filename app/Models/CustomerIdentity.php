<?php

namespace App\Models;

use App\Enums\ChannelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'workspace_id',
        'channel_type',
        'external_id',
        'username',
        'display_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'channel_type' => ChannelType::class,
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}

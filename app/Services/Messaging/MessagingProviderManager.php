<?php

namespace App\Services\Messaging;

use App\Enums\ChannelType;
use App\Models\Channel;
use InvalidArgumentException;

/**
 * Resolves the right MessagingProviderInterface for a channel/provider name.
 * This is the only place that knows which provider class backs which
 * channel types — everything else (Inbox, Settings) goes through here.
 */
class MessagingProviderManager
{
    /** @var array<string, class-string<MessagingProviderInterface>> */
    private array $providers = [
        'meta' => MetaMessagingProvider::class,
    ];

    /** @var array<string, string> channel type value => provider key */
    private array $channelTypeToProvider = [
        'instagram' => 'meta',
        'facebook_messenger' => 'meta',
    ];

    public function driver(string $provider): MessagingProviderInterface
    {
        if (! isset($this->providers[$provider])) {
            throw new InvalidArgumentException("No messaging provider registered for [{$provider}].");
        }

        return app($this->providers[$provider]);
    }

    public function forChannel(Channel $channel): MessagingProviderInterface
    {
        $type = $channel->type instanceof ChannelType ? $channel->type->value : $channel->type;

        $provider = $this->channelTypeToProvider[$type] ?? null;

        if (! $provider) {
            throw new InvalidArgumentException("No messaging provider is registered for channel type [{$type}].");
        }

        return $this->driver($provider);
    }

    public function providerForChannelType(ChannelType $type): ?string
    {
        return $this->channelTypeToProvider[$type->value] ?? null;
    }
}

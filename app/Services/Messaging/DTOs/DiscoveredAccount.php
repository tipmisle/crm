<?php

namespace App\Services\Messaging\DTOs;

use App\Enums\ChannelType;

/**
 * A connectable account/page surfaced after OAuth, before the user has
 * picked which ones to turn into Channels. Kept out of the database —
 * lives only in the signed session payload between callback and store.
 */
final class DiscoveredAccount
{
    public function __construct(
        public readonly ChannelType $channelType,
        public readonly string $externalAccountId,
        public readonly string $displayName,
        public readonly ?string $username,
        public readonly string $pageAccessToken,
        public readonly ?string $parentPageId = null,
    ) {}

    public function toSessionArray(): array
    {
        return [
            'channel_type' => $this->channelType->value,
            'external_account_id' => $this->externalAccountId,
            'display_name' => $this->displayName,
            'username' => $this->username,
            'page_access_token' => $this->pageAccessToken,
            'parent_page_id' => $this->parentPageId,
        ];
    }

    public function toPickerArray(): array
    {
        return [
            'channel_type' => $this->channelType->value,
            'external_account_id' => $this->externalAccountId,
            'display_name' => $this->displayName,
            'username' => $this->username,
        ];
    }

    public static function fromSessionArray(array $data): self
    {
        return new self(
            channelType: ChannelType::from($data['channel_type']),
            externalAccountId: $data['external_account_id'],
            displayName: $data['display_name'],
            username: $data['username'] ?? null,
            pageAccessToken: $data['page_access_token'],
            parentPageId: $data['parent_page_id'] ?? null,
        );
    }
}

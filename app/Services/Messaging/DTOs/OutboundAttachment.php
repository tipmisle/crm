<?php

namespace App\Services\Messaging\DTOs;

final class OutboundAttachment
{
    public function __construct(
        public readonly string $type, // image | video | file
        public readonly string $url, // public URL, stored on the Message for display in our own Inbox
        public readonly string $localPath, // absolute filesystem path, used to upload the bytes directly to Meta
    ) {}

    public function toArray(): array
    {
        return ['type' => $this->type, 'url' => $this->url];
    }
}

<?php

namespace App\Services\Messaging\DTOs;

final class OutboundAttachment
{
    public function __construct(
        public readonly string $type, // image | video | file
        public readonly string $path, // relative path on the private 'local' disk
        public readonly string $localPath, // absolute filesystem path, used to upload the bytes directly to Meta
    ) {}

    /**
     * Stored on Message.metadata (itself application-encrypted — see
     * docs/data-security.md). Deliberately has NO public url: the file
     * lives on the private disk, so the frontend resolves a real URL by
     * hitting the authorized inbox.attachments.show route with the
     * message id + attachment index instead.
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'source' => 'local', 'path' => $this->path];
    }
}

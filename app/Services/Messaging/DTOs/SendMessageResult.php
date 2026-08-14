<?php

namespace App\Services\Messaging\DTOs;

final class SendMessageResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $externalMessageId,
        public readonly ?string $errorMessage,
        public readonly bool $windowExpired,
    ) {}

    public static function success(string $externalMessageId): self
    {
        return new self(true, $externalMessageId, null, false);
    }

    public static function failure(string $errorMessage, bool $windowExpired = false): self
    {
        return new self(false, null, $errorMessage, $windowExpired);
    }
}

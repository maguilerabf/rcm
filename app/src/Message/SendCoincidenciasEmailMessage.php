<?php

namespace App\Message;

final readonly class SendCoincidenciasEmailMessage
{
    /**
     * @param array<int, string> $to
     */
    public function __construct(
        public array $to,
        public ?string $subject = null,
        public ?string $body = null,
        public ?string $sector = null,
    ) {
    }
}

<?php

namespace App\Message;

final readonly class SendDuplicadosEmailMessage
{
    /**
     * @param array<int, string> $to
     * @param array<int, string>|null $matchTypes  null = todos
     */
    public function __construct(
        public array $to,
        public ?string $subject = null,
        public ?string $body = null,
        public ?array $matchTypes = null,
        public ?string $search = null,
    ) {
    }
}

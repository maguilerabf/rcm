<?php

namespace App\Message;

final readonly class SendPasswordResetEmailMessage
{
    public function __construct(
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $resetUrl,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}

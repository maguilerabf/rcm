<?php

namespace App\Message;

final readonly class SendApprovalRequestMessage
{
    public function __construct(
        public int $userId,
        public string $approveUrl,
        public string $rejectUrl,
    ) {
    }
}

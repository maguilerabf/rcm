<?php

namespace App\MessageHandler;

use App\Message\SendPasswordResetEmailMessage;
use App\Service\PasswordResetMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendPasswordResetEmailHandler
{
    public function __construct(
        private PasswordResetMailer $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendPasswordResetEmailMessage $message): void
    {
        try {
            $this->mailer->send(
                $message->email,
                $message->firstName,
                $message->lastName,
                $message->resetUrl,
                $message->expiresAt,
            );
            $this->logger->info('Password reset email sent', ['to' => $message->email]);
        } catch (\Throwable $e) {
            $this->logger->error('Password reset email failed', ['to' => $message->email, 'error' => $e->getMessage()]);
            throw $e;   // dejar que Messenger reintente
        }
    }
}

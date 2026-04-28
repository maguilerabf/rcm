<?php

namespace App\MessageHandler;

use App\Message\SendWelcomeEmailMessage;
use App\Service\WelcomeMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendWelcomeEmailHandler
{
    public function __construct(
        private WelcomeMailer $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendWelcomeEmailMessage $message): void
    {
        try {
            $this->mailer->send($message->email, $message->firstName, $message->lastName);
            $this->logger->info('Welcome email sent', ['to' => $message->email]);
        } catch (\Throwable $e) {
            // No relanzo para que el registro no se considere fallado por un email caído.
            $this->logger->error('Welcome email failed', ['to' => $message->email, 'error' => $e->getMessage()]);
        }
    }
}

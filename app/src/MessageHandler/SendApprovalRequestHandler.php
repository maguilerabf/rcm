<?php

namespace App\MessageHandler;

use App\Message\SendApprovalRequestMessage;
use App\Repository\UserRepository;
use App\Service\UserApprovalMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendApprovalRequestHandler
{
    public function __construct(
        private UserApprovalMailer $mailer,
        private UserRepository $users,
        private LoggerInterface $logger,
        private string $superAdminEmail,
    ) {
    }

    public function __invoke(SendApprovalRequestMessage $message): void
    {
        $user = $this->users->find($message->userId);
        if (!$user) {
            $this->logger->warning('approval mail: user not found', ['user_id' => $message->userId]);
            return;
        }
        try {
            $this->mailer->sendApprovalRequest($this->superAdminEmail, $user, $message->approveUrl, $message->rejectUrl);
        } catch (\Throwable $e) {
            $this->logger->error('approval mail failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}

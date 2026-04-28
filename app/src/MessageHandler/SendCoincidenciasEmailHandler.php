<?php

namespace App\MessageHandler;

use App\Message\SendCoincidenciasEmailMessage;
use App\Service\CoincidenciasMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendCoincidenciasEmailHandler
{
    public function __construct(private CoincidenciasMailer $mailer)
    {
    }

    public function __invoke(SendCoincidenciasEmailMessage $message): void
    {
        $this->mailer->sendCoincidencias(
            to: $message->to,
            subject: $message->subject,
            body: $message->body,
            sector: $message->sector,
        );
    }
}

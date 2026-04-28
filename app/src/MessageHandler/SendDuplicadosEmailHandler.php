<?php

namespace App\MessageHandler;

use App\Message\SendDuplicadosEmailMessage;
use App\Service\DuplicadosMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendDuplicadosEmailHandler
{
    public function __construct(private DuplicadosMailer $mailer)
    {
    }

    public function __invoke(SendDuplicadosEmailMessage $message): void
    {
        $this->mailer->sendDuplicados(
            to: $message->to,
            subject: $message->subject,
            body: $message->body,
            matchTypes: $message->matchTypes,
            search: $message->search,
        );
    }
}

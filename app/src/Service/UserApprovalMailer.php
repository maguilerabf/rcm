<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class UserApprovalMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailerFrom,
    ) {
    }

    public function sendApprovalRequest(string $to, User $applicant, string $approveUrl, string $rejectUrl): void
    {
        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($to)
            ->subject("RCM — solicitud de registro: {$applicant->getDisplayName()}")
            ->htmlTemplate('emails/approval_request.html.twig')
            ->context([
                'applicant'  => $applicant,
                'approveUrl' => $approveUrl,
                'rejectUrl'  => $rejectUrl,
            ]);

        $this->mailer->send($email);
    }
}

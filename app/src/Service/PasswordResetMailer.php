<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class PasswordResetMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailerFrom,
    ) {
    }

    public function send(string $to, string $firstName, string $lastName, string $resetUrl, \DateTimeImmutable $expiresAt): void
    {
        $displayName = trim($firstName . ' ' . $lastName) ?: 'usuario(a)';

        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($to)
            ->subject('Restablecer tu contraseña en RCM')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'displayName'    => $displayName,
                'recipientEmail' => $to,
                'resetUrl'       => $resetUrl,
                'expiresAt'      => $expiresAt,
                'expiresInText'  => 'una hora',
            ]);

        $this->mailer->send($email);
    }
}

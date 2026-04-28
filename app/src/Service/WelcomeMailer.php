<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class WelcomeMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailerFrom,
        private readonly string $appUrl = 'https://rcm.iaflow.cl',
    ) {
    }

    public function send(string $to, string $firstName, string $lastName): void
    {
        $displayName = trim($firstName . ' ' . $lastName) ?: 'usuario(a)';
        $loginUrl = rtrim($this->appUrl, '/') . '/login';

        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($to)
            ->subject('¡Bienvenido a RCM!')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context([
                'displayName' => $displayName,
                'email'       => $to,
                'loginUrl'    => $loginUrl,
            ]);

        $this->mailer->send($email);
    }
}

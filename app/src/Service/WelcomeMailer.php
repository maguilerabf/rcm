<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

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
        $displayName = trim($firstName . ' ' . $lastName);

        $body = <<<TEXT
        Hola {$displayName},

        Tu cuenta en RCM ha sido creada con éxito.

        Acceso:        {$this->appUrl}/login
        Email:         {$to}

        Por seguridad, no compartas tus credenciales y cambia tu contraseña periódicamente.

        --
        Equipo RCM
        TEXT;

        $email = (new Email())
            ->from(Address::create($this->mailerFrom))
            ->to($to)
            ->subject('¡Bienvenido a RCM!')
            ->text($body);

        $this->mailer->send($email);
    }
}

<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class CoincidenciasMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly CoincidenciasExporter $exporter,
        private readonly string $uploadDir,
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * Genera el xlsx en un tmp file y lo envía como adjunto.
     *
     * @param array<int, string> $to
     */
    public function sendCoincidencias(array $to, ?string $subject, ?string $body, ?string $sector = null): int
    {
        if (empty($to)) {
            throw new \InvalidArgumentException('Destinatario requerido.');
        }

        $tmpDir = $this->uploadDir . '/exports';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $filename = $this->exporter->suggestFilename($sector);
        $tmpPath = $tmpDir . '/' . $filename;

        $rows = $this->exporter->writeToPath($tmpPath, $sector);

        try {
            $email = (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->subject($subject ?: 'Coincidencias - Identificación Sectores')
                ->htmlTemplate('emails/coincidencias.html.twig')
                ->context([
                    'rows'       => $rows,
                    'sector'     => $sector,
                    'customBody' => $body,
                ])
                ->attachFromPath($tmpPath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            foreach ($to as $recipient) {
                $email->addTo($recipient);
            }

            $this->mailer->send($email);
        } finally {
            @unlink($tmpPath);
        }

        return $rows;
    }
}

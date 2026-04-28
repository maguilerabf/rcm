<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class DuplicadosMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly DuplicadosExporter $exporter,
        private readonly string $uploadDir,
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * Genera xlsx con duplicados detectados y lo envía como adjunto.
     *
     * @param array<int, string> $to
     * @param array<int, string>|null $matchTypes  null = todos
     */
    public function sendDuplicados(array $to, ?string $subject, ?string $body, ?array $matchTypes = null, ?string $search = null): int
    {
        if (empty($to)) {
            throw new \InvalidArgumentException('Destinatario requerido.');
        }

        $tmpDir = $this->uploadDir . '/exports';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $filename = $this->exporter->suggestFilename();
        $tmpPath = $tmpDir . '/' . $filename;

        $rows = $this->exporter->writeToPath($tmpPath, $matchTypes, $search);

        try {
            $email = (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->subject($subject ?: 'Duplicados Inscritos - RCM')
                ->htmlTemplate('emails/duplicados.html.twig')
                ->context([
                    'rows'       => $rows,
                    'matchTypes' => $matchTypes ?? [],
                    'search'     => $search,
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

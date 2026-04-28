<?php

namespace App\MessageHandler;

use App\Entity\ImportJob;
use App\Message\ImportInscritosMessage;
use App\Repository\ImportJobRepository;
use App\Service\InscritosImporter;
use App\Service\StorageCleanupService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ImportInscritosHandler
{
    public function __construct(
        private ImportJobRepository $jobs,
        private InscritosImporter $importer,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private StorageCleanupService $cleanup,
    ) {
    }

    public function __invoke(ImportInscritosMessage $message): void
    {
        $job = $this->jobs->findOneByUuid($message->jobId);
        if (!$job || $job->getKind() !== ImportJob::KIND_INSCRITOS) {
            $this->logger->warning('ImportInscritosHandler: job not found', ['jobId' => $message->jobId]);
            return;
        }

        $job->markStarted();
        $this->em->flush();

        try {
            $rows = $this->importer->import($job);
            $job->markDone($rows);
            $this->em->flush();
            $this->jobs->activateExclusive($job);

            // Cleanup automático: si superamos umbral, borra jobs viejos. No bloquea ni rompe el import.
            try {
                $this->cleanup->cleanup();
            } catch (\Throwable $e) {
                $this->logger->warning('Storage cleanup after import failed', ['error' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());
            $this->em->flush();
            $this->logger->error('Inscritos import failed', ['jobId' => $message->jobId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}

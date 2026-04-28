<?php

namespace App\Service;

use App\Entity\ImportJob;
use App\Repository\ImportJobRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Mide el almacenamiento usado por la app (tablas + uploads en disco) y, cuando supera
 * el umbral, borra los import_jobs más antiguos hasta volver al objetivo.
 *
 * NUNCA borra:
 *   - Jobs activos (`is_active = true`)
 *   - Jobs en proceso (status pending|running)
 */
class StorageCleanupService
{
    public function __construct(
        private readonly Connection $db,
        private readonly EntityManagerInterface $em,
        private readonly ImportJobRepository $jobs,
        private readonly LoggerInterface $logger,
        private readonly string $uploadDir,
        private readonly int $thresholdBytes,
        private readonly int $targetBytes,
    ) {
    }

    /**
     * @return array{dbBytes:int, diskBytes:int, totalBytes:int, thresholdBytes:int, targetBytes:int}
     */
    public function getCurrentUsage(): array
    {
        $dbBytes = (int) $this->db->fetchOne("
            SELECT COALESCE(SUM(pg_total_relation_size(c.oid)), 0)
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relname IN ('inscrito', 'telesalud_solicitud', 'import_job', 'messenger_messages')
        ");
        $diskBytes = $this->getDirectorySize($this->uploadDir);

        return [
            'dbBytes' => $dbBytes,
            'diskBytes' => $diskBytes,
            'totalBytes' => $dbBytes + $diskBytes,
            'thresholdBytes' => $this->thresholdBytes,
            'targetBytes' => $this->targetBytes,
        ];
    }

    /**
     * Ejecuta cleanup si el total supera el umbral.
     *
     * @return array{triggered:bool, usageBefore:array, usageAfter:array, deleted:array<int, array<string, mixed>>, skipped:int}
     */
    public function cleanup(): array
    {
        $usageBefore = $this->getCurrentUsage();
        $deleted = [];
        $skipped = 0;

        if ($usageBefore['totalBytes'] <= $this->thresholdBytes) {
            return [
                'triggered' => false,
                'usageBefore' => $usageBefore,
                'usageAfter' => $usageBefore,
                'deleted' => $deleted,
                'skipped' => 0,
            ];
        }

        $this->logger->info('Storage cleanup triggered', [
            'totalBytes' => $usageBefore['totalBytes'],
            'thresholdBytes' => $this->thresholdBytes,
            'targetBytes' => $this->targetBytes,
        ]);

        // Candidatos: NO activos, NO en proceso. Más antiguos primero.
        $candidates = $this->em->createQueryBuilder()
            ->select('j')
            ->from(ImportJob::class, 'j')
            ->where('j.active = :inactive')
            ->andWhere('j.status NOT IN (:protectedStatuses)')
            ->setParameter('inactive', false)
            ->setParameter('protectedStatuses', [ImportJob::STATUS_PENDING, ImportJob::STATUS_RUNNING])
            ->orderBy('j.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($candidates as $job) {
            $current = $this->getCurrentUsage();
            if ($current['totalBytes'] <= $this->targetBytes) break;

            $path = $job->getStoredPath();
            $info = [
                'id' => $job->getId()->toRfc4122(),
                'kind' => $job->getKind(),
                'originalFilename' => $job->getOriginalFilename(),
                'createdAt' => $job->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'rowsImported' => $job->getRowsImported(),
            ];

            try {
                $this->em->remove($job);
                $this->em->flush();
                if ($path && is_file($path)) {
                    @unlink($path);
                }
                $deleted[] = $info;
                $this->logger->info('Storage cleanup: deleted job', $info);
            } catch (\Throwable $e) {
                $skipped++;
                $this->logger->warning('Storage cleanup: failed to delete job', $info + ['error' => $e->getMessage()]);
            }
        }

        $usageAfter = $this->getCurrentUsage();

        if ($usageAfter['totalBytes'] > $this->targetBytes) {
            $this->logger->warning('Storage cleanup: still above target after deleting all candidates', [
                'totalBytesAfter' => $usageAfter['totalBytes'],
                'targetBytes' => $this->targetBytes,
                'deletedCount' => count($deleted),
                'note' => 'Active jobs and in-progress jobs are protected and not deleted.',
            ]);
        }

        return [
            'triggered' => true,
            'usageBefore' => $usageBefore,
            'usageAfter' => $usageAfter,
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }

    private function getDirectorySize(string $dir): int
    {
        if (!is_dir($dir)) return 0;
        $size = 0;
        try {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
            );
            foreach ($iter as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to walk uploads dir', ['error' => $e->getMessage()]);
        }
        return $size;
    }
}

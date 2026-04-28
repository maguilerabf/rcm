<?php

namespace App\Controller\Api;

use App\Entity\ImportJob;
use App\Repository\ImportJobRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/identificacion-sectores')]
class JobHistoryController extends AbstractController
{
    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        #[Autowire('%app.upload_dir%')]
        private readonly string $uploadDir,
    ) {
    }

    #[Route('/jobs/history', name: 'api_jobs_history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        $jobs = $this->jobs->listAll();

        $rowCounts = $this->db->fetchAllKeyValue(
            'SELECT import_job_id::text, COUNT(*) FROM telesalud_solicitud GROUP BY import_job_id
             UNION ALL
             SELECT import_job_id::text, COUNT(*) FROM inscrito GROUP BY import_job_id'
        );

        $data = array_map(fn (ImportJob $j) => $this->serialize($j, (int) ($rowCounts[$j->getId()->toRfc4122()] ?? 0)), $jobs);

        return new JsonResponse([
            'jobs' => $data,
            'totalRows' => array_sum(array_column($data, 'rowsInDb')),
        ]);
    }

    #[Route('/jobs/{id}/activate', name: 'api_job_activate', methods: ['POST'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function activate(string $id): JsonResponse
    {
        $job = $this->jobs->findOneByUuid($id);
        if (!$job) {
            return new JsonResponse(['error' => 'Job no existe.'], Response::HTTP_NOT_FOUND);
        }
        if ($job->getStatus() !== ImportJob::STATUS_DONE) {
            return new JsonResponse(['error' => 'Solo se pueden activar cargas con estado "done".'], Response::HTTP_BAD_REQUEST);
        }

        $this->jobs->activateExclusive($job);
        return new JsonResponse(['ok' => true, 'job' => $this->serialize($job, null)]);
    }

    #[Route('/jobs/{id}', name: 'api_job_delete', methods: ['DELETE'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function delete(string $id): JsonResponse
    {
        $job = $this->jobs->findOneByUuid($id);
        if (!$job) {
            return new JsonResponse(['error' => 'Job no existe.'], Response::HTTP_NOT_FOUND);
        }

        $wasActive = $job->isActive();
        $kind = $job->getKind();
        $path = $job->getStoredPath();

        $this->em->remove($job);
        $this->em->flush();

        if ($path && is_file($path)) {
            @unlink($path);
        }

        // Si borraste el activo, intenta promover al ultimo done que quede.
        if ($wasActive) {
            $next = $this->jobs->findLatestDoneByKind($kind);
            if ($next) {
                $this->jobs->activateExclusive($next);
            }
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/jobs', name: 'api_jobs_delete_all', methods: ['DELETE'])]
    public function deleteAll(): JsonResponse
    {
        // Borra todas las filas y todos los archivos de uploads (telesalud + inscritos).
        // Las tablas hijas (inscrito, telesalud_solicitud) caen por ON DELETE CASCADE.
        $this->em->getConnection()->executeStatement('DELETE FROM import_job');

        foreach (['telesalud', 'inscritos', 'exports'] as $sub) {
            $dir = $this->uploadDir . '/' . $sub;
            if (!is_dir($dir)) continue;
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file)) @unlink($file);
            }
        }

        return new JsonResponse(['ok' => true]);
    }

    private function serialize(ImportJob $job, ?int $rowsInDb): array
    {
        $createdBy = $job->getCreatedBy();
        return [
            'id' => $job->getId()->toRfc4122(),
            'kind' => $job->getKind(),
            'status' => $job->getStatus(),
            'isActive' => $job->isActive(),
            'originalFilename' => $job->getOriginalFilename(),
            'rowsImported' => $job->getRowsImported(),
            'rowsInDb' => $rowsInDb,
            'error' => $job->getError(),
            'createdAt' => $job->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'startedAt' => $job->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(\DateTimeInterface::ATOM),
            'createdBy' => $createdBy ? [
                'id' => $createdBy->getId(),
                'displayName' => $createdBy->getDisplayName(),
                'email' => $createdBy->getEmail(),
            ] : null,
        ];
    }
}

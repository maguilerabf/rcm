<?php

namespace App\Controller\Api;

use App\Entity\ImportJob;
use App\Entity\User;
use App\Message\ImportInscritosMessage;
use App\Message\ImportTelesaludMessage;
use App\Repository\ImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/identificacion-sectores')]
class ImportController extends AbstractController
{
    private const ALLOWED_KINDS = [
        'telesalud' => ImportJob::KIND_TELESALUD,
        'inscritos' => ImportJob::KIND_INSCRITOS,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImportJobRepository $jobs,
        private readonly MessageBusInterface $bus,
        #[Autowire('%app.upload_dir%')]
        private readonly string $uploadDir,
    ) {
    }

    #[Route('/upload/{kind}', name: 'api_upload', methods: ['POST'], requirements: ['kind' => 'telesalud|inscritos'])]
    public function upload(string $kind, Request $request): JsonResponse
    {
        $kindEnum = self::ALLOWED_KINDS[$kind] ?? null;
        if (!$kindEnum) {
            return new JsonResponse(['error' => 'Tipo inválido.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'Falta el archivo.'], Response::HTTP_BAD_REQUEST);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            return new JsonResponse(['error' => 'Sólo se aceptan archivos .xlsx o .csv.'], Response::HTTP_BAD_REQUEST);
        }

        $targetDir = $this->uploadDir . '/' . $kind;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $storedName = (new \DateTimeImmutable())->format('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        try {
            $file->move($targetDir, $storedName);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'No se pudo guardar el archivo: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $job = new ImportJob($kindEnum, $file->getClientOriginalName(), $targetDir . '/' . $storedName);
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user) {
            $job->setCreatedBy($user);
        }
        $this->em->persist($job);
        $this->em->flush();

        $message = $kindEnum === ImportJob::KIND_TELESALUD
            ? new ImportTelesaludMessage($job->getId()->toRfc4122())
            : new ImportInscritosMessage($job->getId()->toRfc4122());
        $this->bus->dispatch($message);

        return new JsonResponse([
            'job' => $this->serializeJob($job),
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/jobs/{id}', name: 'api_job_status', methods: ['GET'], requirements: ['id' => '[0-9a-f-]{36}'])]
    public function status(string $id): JsonResponse
    {
        $job = $this->jobs->findOneByUuid($id);
        if (!$job) {
            return new JsonResponse(['error' => 'Job no existe.'], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse(['job' => $this->serializeJob($job)]);
    }

    #[Route('/jobs', name: 'api_jobs_latest', methods: ['GET'])]
    public function latest(): JsonResponse
    {
        return new JsonResponse([
            'telesalud' => $this->serializeJobOrNull($this->jobs->findActiveByKind(ImportJob::KIND_TELESALUD)),
            'inscritos' => $this->serializeJobOrNull($this->jobs->findActiveByKind(ImportJob::KIND_INSCRITOS)),
        ]);
    }

    private function serializeJob(ImportJob $job): array
    {
        return [
            'id' => $job->getId()->toRfc4122(),
            'kind' => $job->getKind(),
            'status' => $job->getStatus(),
            'originalFilename' => $job->getOriginalFilename(),
            'rowsTotal' => $job->getRowsTotal(),
            'rowsImported' => $job->getRowsImported(),
            'error' => $job->getError(),
            'createdAt' => $job->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'startedAt' => $job->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeJobOrNull(?ImportJob $job): ?array
    {
        return $job ? $this->serializeJob($job) : null;
    }
}

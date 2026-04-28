<?php

namespace App\Repository;

use App\Entity\ImportJob;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ImportJob>
 */
class ImportJobRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportJob::class);
    }

    /**
     * Devuelve el job activo del kind dado (el "pinneado" por el usuario o el ultimo done auto-activado).
     */
    public function findActiveByKind(string $kind): ?ImportJob
    {
        return $this->findOneBy(['kind' => $kind, 'active' => true]);
    }

    /**
     * Fallback: ultimo done por kind (cuando se borra el activo y hay que elegir uno nuevo).
     */
    public function findLatestDoneByKind(string $kind): ?ImportJob
    {
        return $this->createQueryBuilder('j')
            ->where('j.kind = :kind')
            ->andWhere('j.status = :status')
            ->setParameter('kind', $kind)
            ->setParameter('status', ImportJob::STATUS_DONE)
            ->orderBy('j.finishedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ImportJob[]
     */
    public function listAll(): array
    {
        return $this->createQueryBuilder('j')
            ->orderBy('j.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUuid(string|Uuid $id): ?ImportJob
    {
        $uuid = $id instanceof Uuid ? $id : Uuid::fromString($id);
        return $this->find($uuid);
    }

    /**
     * Marca como activo el job dado y desactiva todos los demas del mismo kind, en una transaccion.
     */
    public function activateExclusive(ImportJob $job): void
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();
        $conn->transactional(function () use ($conn, $job): void {
            $conn->executeStatement(
                'UPDATE import_job SET is_active = FALSE WHERE kind = :kind AND id <> :id',
                ['kind' => $job->getKind(), 'id' => $job->getId()->toRfc4122()],
            );
            $conn->executeStatement(
                'UPDATE import_job SET is_active = TRUE WHERE id = :id',
                ['id' => $job->getId()->toRfc4122()],
            );
        });
        // refrescar la entity en memoria por si Doctrine la cacheo
        $em->refresh($job);
    }
}

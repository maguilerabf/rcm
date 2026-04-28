<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findActiveByHash(string $tokenHash): ?PasswordResetToken
    {
        $now = new \DateTimeImmutable();
        return $this->createQueryBuilder('t')
            ->andWhere('t.tokenHash = :h')
            ->andWhere('t.usedAt IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('h', $tokenHash)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countRecentForUser(User $user, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.user = :u')
            ->andWhere('t.createdAt >= :since')
            ->setParameter('u', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestForUser(User $user): ?PasswordResetToken
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :u')
            ->setParameter('u', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function invalidateAllForUser(User $user): int
    {
        $now = new \DateTimeImmutable();
        return (int) $this->createQueryBuilder('t')
            ->update()
            ->set('t.usedAt', ':now')
            ->andWhere('t.user = :u')
            ->andWhere('t.usedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('u', $user)
            ->getQuery()
            ->execute();
    }

    public function deleteExpiredOlderThan(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.expiresAt < :c')
            ->setParameter('c', $cutoff)
            ->getQuery()
            ->execute();
    }
}

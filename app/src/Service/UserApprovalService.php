<?php

namespace App\Service;

use App\Entity\User;
use App\Message\SendApprovalRequestMessage;
use App\Message\SendWelcomeEmailMessage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Aprueba/rechaza registros nuevos. Solo el super-admin (m.aguilera89@gmail.com)
 * puede operar este servicio (controlado en el controlador).
 */
class UserApprovalService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly MessageBusInterface $bus,
        private readonly string $appUrl = 'https://rcm.iaflow.cl',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function notifyPendingRegistration(User $user): void
    {
        if (!$user->isPending()) return;

        $token = bin2hex(random_bytes(32));
        $user->setApprovalToken($token);
        $this->em->flush();

        $base = rtrim($this->appUrl, '/');
        $approveUrl = $base . '/admin/aprobar/' . $token . '?action=approve';
        $rejectUrl  = $base . '/admin/aprobar/' . $token . '?action=reject';

        $this->bus->dispatch(new SendApprovalRequestMessage(
            userId: $user->getId(),
            approveUrl: $approveUrl,
            rejectUrl: $rejectUrl,
        ));
    }

    /**
     * @param array<string> $roles
     */
    public function approve(User $user, User $approver, array $roles): void
    {
        if ($user->isActive()) return;

        $clean = array_values(array_unique(array_filter($roles, fn ($r) => is_string($r) && str_starts_with($r, 'ROLE_'))));
        if ($clean === []) $clean = ['ROLE_USER'];

        $user->setRoles($clean);
        $user->setStatus(User::STATUS_ACTIVE);
        $user->setApprovedBy($approver);
        $user->setApprovedAt(new \DateTimeImmutable());
        $user->setApprovalToken(null);
        $this->em->flush();

        $this->bus->dispatch(new SendWelcomeEmailMessage(
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
        ));
    }

    public function reject(User $user, User $approver): void
    {
        if ($user->isRejected()) return;
        $user->setStatus(User::STATUS_REJECTED);
        $user->setApprovedBy($approver);
        $user->setApprovedAt(new \DateTimeImmutable());
        $user->setApprovalToken(null);
        $this->em->flush();
    }

    public function findByApprovalToken(string $token): ?User
    {
        $token = trim($token);
        if (strlen($token) !== 64 || !ctype_xdigit($token)) return null;
        return $this->users->findOneByApprovalToken($token);
    }
}

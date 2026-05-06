<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\SuperAdminVoter;
use App\Service\UserApprovalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/users')]
class AdminUsersController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserApprovalService $approval,
        private readonly EntityManagerInterface $em,
        private readonly string $superAdminEmail,
    ) {
    }

    private function assertSuperAdmin(): void
    {
        if (!$this->isGranted(SuperAdminVoter::IS_SUPER_ADMIN)) {
            throw $this->createAccessDeniedException('Solo el super-admin puede gestionar usuarios.');
        }
    }

    #[Route('', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->assertSuperAdmin();
        return new JsonResponse([
            'users' => array_map(fn (User $u) => $this->serialize($u), $this->users->findAllOrdered()),
            'pendingCount' => $this->users->countPending(),
        ]);
    }

    #[Route('/pending-count', name: 'api_admin_users_pending', methods: ['GET'])]
    public function pendingCount(): JsonResponse
    {
        $this->assertSuperAdmin();
        return new JsonResponse(['count' => $this->users->countPending()]);
    }

    #[Route('/{id}/approve', name: 'api_admin_users_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        $this->assertSuperAdmin();
        $user = $this->users->find($id);
        if (!$user) return new JsonResponse(['error' => 'Usuario no encontrado.'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent() ?: '{}', true) ?: [];
        $roles = $data['roles'] ?? ['ROLE_USER'];
        if (!is_array($roles)) $roles = [$roles];

        /** @var User $current */
        $current = $this->getUser();
        $this->approval->approve($user, $current, $roles);

        return new JsonResponse(['user' => $this->serialize($user)]);
    }

    #[Route('/{id}/reject', name: 'api_admin_users_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reject(int $id): JsonResponse
    {
        $this->assertSuperAdmin();
        $user = $this->users->find($id);
        if (!$user) return new JsonResponse(['error' => 'Usuario no encontrado.'], Response::HTTP_NOT_FOUND);

        /** @var User $current */
        $current = $this->getUser();
        $this->approval->reject($user, $current);

        return new JsonResponse(['user' => $this->serialize($user)]);
    }

    #[Route('/{id}', name: 'api_admin_users_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->assertSuperAdmin();
        $user = $this->users->find($id);
        if (!$user) return new JsonResponse(['error' => 'Usuario no encontrado.'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent() ?: '{}', true) ?: [];

        if (array_key_exists('roles', $data)) {
            $roles = $data['roles'];
            if (!is_array($roles)) $roles = [$roles];
            $clean = array_values(array_unique(array_filter($roles, fn ($r) => is_string($r) && str_starts_with($r, 'ROLE_'))));
            if ($clean === []) $clean = ['ROLE_USER'];
            $user->setRoles($clean);
        }

        $this->em->flush();
        return new JsonResponse(['user' => $this->serialize($user)]);
    }

    #[Route('/by-token/{token}', name: 'api_admin_users_by_token_get', methods: ['GET'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function byTokenGet(string $token): JsonResponse
    {
        $this->assertSuperAdmin();
        $user = $this->approval->findByApprovalToken($token);
        if (!$user) return new JsonResponse(['error' => 'Token inválido o ya consumido.'], Response::HTTP_NOT_FOUND);
        return new JsonResponse(['user' => $this->serialize($user)]);
    }

    #[Route('/by-token/{token}', name: 'api_admin_users_by_token', methods: ['POST'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function byToken(string $token, Request $request): JsonResponse
    {
        $this->assertSuperAdmin();
        $user = $this->approval->findByApprovalToken($token);
        if (!$user) return new JsonResponse(['error' => 'Token inválido o ya consumido.'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent() ?: '{}', true) ?: [];
        $action = (string) ($data['action'] ?? '');

        /** @var User $current */
        $current = $this->getUser();

        if ($action === 'approve') {
            $roles = $data['roles'] ?? ['ROLE_USER'];
            if (!is_array($roles)) $roles = [$roles];
            $this->approval->approve($user, $current, $roles);
        } elseif ($action === 'reject') {
            $this->approval->reject($user, $current);
        } else {
            return new JsonResponse(['error' => 'Acción inválida.'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['user' => $this->serialize($user)]);
    }

    private function serialize(User $u): array
    {
        return [
            'id' => $u->getId(),
            'email' => $u->getEmail(),
            'firstName' => $u->getFirstName(),
            'lastName' => $u->getLastName(),
            'displayName' => $u->getDisplayName(),
            'roles' => $u->getRoles(),
            'status' => $u->getStatus(),
            'isSuperAdmin' => strcasecmp($u->getEmail(), $this->superAdminEmail) === 0,
            'approvedAt' => $u->getApprovedAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $u->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AuthController extends AbstractController
{
    public function __construct(private readonly string $superAdminEmail = 'm.aguilera89@gmail.com')
    {
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['user' => null], 200);
        }

        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'displayName' => $user->getDisplayName(),
                'roles' => $user->getRoles(),
                'status' => $user->getStatus(),
                'isSuperAdmin' => strcasecmp($user->getEmail(), $this->superAdminEmail) === 0,
            ],
        ]);
    }

    #[Route('/api/csrf', name: 'api_csrf', methods: ['GET'])]
    public function csrf(CsrfTokenManagerInterface $csrf): JsonResponse
    {
        return new JsonResponse(['token' => $csrf->getToken('rcm')->getValue()]);
    }
}

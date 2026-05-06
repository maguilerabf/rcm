<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class JsonAuthSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly string $superAdminEmail = 'm.aguilera89@gmail.com')
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var User $user */
        $user = $token->getUser();

        if (!$user->isActive()) {
            if ($request->hasSession() && $request->getSession()->isStarted()) {
                $request->getSession()->invalidate();
            }
            $msg = $user->isPending()
                ? 'Tu cuenta está pendiente de aprobación por el administrador. Revisá tu correo.'
                : 'Tu cuenta no está habilitada para acceder. Contactá al administrador.';
            return new JsonResponse(['error' => $msg], Response::HTTP_FORBIDDEN);
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
}

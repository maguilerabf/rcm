<?php

namespace App\Controller\Api;

use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $service,
    ) {
    }

    /**
     * Pide un correo de recuperación. Respuesta siempre uniforme para evitar
     * email enumeration: igual sea que el email exista o no, devolvemos el
     * mismo mensaje genérico.
     */
    #[Route('/api/password/request', name: 'api_password_request', methods: ['POST'])]
    public function request(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent() ?: '{}', true) ?: [];
        $email = (string) ($data['email'] ?? '');

        // Si el email viene vacío sí devolvemos un error de validación claro,
        // para no quedar mudos ante typos del frontend.
        if (trim($email) === '') {
            return new JsonResponse(['error' => 'El correo es requerido.'], Response::HTTP_BAD_REQUEST);
        }

        $this->service->requestReset(
            email: $email,
            ip: $request->getClientIp(),
            ua: $request->headers->get('User-Agent'),
        );

        return new JsonResponse([
            'ok' => true,
            'message' => 'Si el correo está registrado, te enviaremos un enlace para restablecer tu contraseña en los próximos minutos.',
        ]);
    }

    /**
     * Verifica si un token sigue siendo válido (sin consumirlo).
     * Sirve para que el frontend pueda mostrar el formulario o un error.
     */
    #[Route('/api/password/validate', name: 'api_password_validate', methods: ['GET'])]
    public function validate(Request $request): JsonResponse
    {
        $token = (string) $request->query->get('token', '');
        $user  = $this->service->findUserByToken($token);

        if (!$user) {
            return new JsonResponse(['valid' => false], Response::HTTP_OK);
        }

        return new JsonResponse([
            'valid' => true,
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
        ]);
    }

    /**
     * Aplica el cambio de contraseña.
     */
    #[Route('/api/password/reset', name: 'api_password_reset', methods: ['POST'])]
    public function reset(Request $request): JsonResponse
    {
        $data     = json_decode($request->getContent() ?: '{}', true) ?: [];
        $token    = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if (strlen($password) < 8) {
            return new JsonResponse(['error' => 'La contraseña debe tener al menos 8 caracteres.'], Response::HTTP_BAD_REQUEST);
        }

        $ok = $this->service->resetPassword($token, $password);
        if (!$ok) {
            return new JsonResponse([
                'error' => 'El enlace es inválido o expiró. Pide uno nuevo desde la pantalla de inicio de sesión.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Si el navegador tenía una sesión PHP activa, la cerramos para forzar nuevo login.
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->invalidate();
        }

        return new JsonResponse([
            'ok' => true,
            'message' => 'Tu contraseña fue actualizada. Ya puedes iniciar sesión con la nueva.',
        ]);
    }
}

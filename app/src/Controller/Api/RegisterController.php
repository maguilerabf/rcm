<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserApprovalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
        private readonly UserApprovalService $approval,
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true) ?: [];
        $firstName = trim((string) ($data['firstName'] ?? ''));
        $lastName = trim((string) ($data['lastName'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        $errors = [];
        if ($firstName === '') $errors[] = 'Nombre requerido.';
        if ($lastName === '') $errors[] = 'Apellido requerido.';
        if ($email === '') $errors[] = 'Email requerido.';
        if (strlen($password) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';

        if ($email !== '' && count($this->validator->validate($email, [new Assert\Email()])) > 0) {
            $errors[] = 'Email inválido.';
        }

        if ($errors) {
            return new JsonResponse(['error' => implode(' ', $errors)], Response::HTTP_BAD_REQUEST);
        }

        if ($this->users->findOneBy(['email' => $email])) {
            return new JsonResponse(['error' => 'Ya existe una cuenta con ese correo.'], Response::HTTP_CONFLICT);
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles(['ROLE_USER'])
            ->setStatus(User::STATUS_PENDING);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        // Mail al super-admin para aprobar/rechazar.
        $this->approval->notifyPendingRegistration($user);

        // No autologin: la cuenta queda pendiente.
        return new JsonResponse([
            'pending' => true,
            'message' => 'Tu solicitud fue enviada. Cuando el administrador la apruebe vas a recibir un correo y podrás iniciar sesión.',
        ], Response::HTTP_CREATED);
    }
}

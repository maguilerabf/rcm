<?php

namespace App\Service;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Message\SendPasswordResetEmailMessage;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetService
{
    public const TOKEN_TTL = 'PT1H';            // 1 hora
    public const COOLDOWN_SECONDS = 60;          // mínimo entre requests del mismo user
    public const RATE_WINDOW_SECONDS = 900;      // 15 min
    public const RATE_MAX_REQUESTS = 3;          // máx 3 mails por usuario en ventana

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly MessageBusInterface $bus,
        private readonly string $appUrl = 'https://rcm.iaflow.cl',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Pide un reset. Nunca revela si el email existe o si fue rate-limited:
     * el caller siempre debe responder lo mismo al usuario.
     */
    public function requestReset(string $email, ?string $ip = null, ?string $ua = null): void
    {
        $email = strtolower(trim($email));
        if ($email === '') return;

        $user = $this->users->findOneBy(['email' => $email]);
        if (!$user) {
            // Email enumeration: silencioso. No log warn (es flujo normal).
            return;
        }

        $now = new \DateTimeImmutable();

        // Cooldown: si pidió uno hace menos de COOLDOWN_SECONDS y aún está activo, no spamear.
        $latest = $this->tokens->findLatestForUser($user);
        if ($latest && $latest->isActive($now)) {
            $age = $now->getTimestamp() - $latest->getCreatedAt()->getTimestamp();
            if ($age < self::COOLDOWN_SECONDS) {
                $this->logger->info('password reset cooldown', ['user_id' => $user->getId(), 'age_s' => $age]);
                return;
            }
        }

        // Rate limit por usuario en ventana sliding.
        $windowStart = $now->modify('-' . self::RATE_WINDOW_SECONDS . ' seconds');
        $recent = $this->tokens->countRecentForUser($user, $windowStart);
        if ($recent >= self::RATE_MAX_REQUESTS) {
            $this->logger->warning('password reset rate-limited', [
                'user_id' => $user->getId(),
                'recent'  => $recent,
                'window_s' => self::RATE_WINDOW_SECONDS,
            ]);
            return;
        }

        // Generar token plano (lo que va en el correo) + hash (lo que se guarda).
        $plainToken = bin2hex(random_bytes(32));   // 64 chars hex
        $tokenHash  = hash('sha256', $plainToken);
        $expiresAt  = $now->add(new \DateInterval(self::TOKEN_TTL));

        $token = new PasswordResetToken($user, $tokenHash, $expiresAt, $ip, $ua);
        $this->em->persist($token);
        $this->em->flush();

        $resetUrl = rtrim($this->appUrl, '/') . '/recuperar/' . $plainToken;

        // Mail async — no bloquea el response.
        $this->bus->dispatch(new SendPasswordResetEmailMessage(
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            resetUrl: $resetUrl,
            expiresAt: $expiresAt,
        ));
    }

    /**
     * Verifica un token plano (el que viene del link).
     */
    public function findUserByToken(string $plainToken): ?User
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '' || !ctype_xdigit($plainToken) || strlen($plainToken) !== 64) {
            return null;
        }
        $token = $this->tokens->findActiveByHash(hash('sha256', $plainToken));
        return $token?->getUser();
    }

    /**
     * Aplica el cambio de contraseña. Devuelve true si fue exitoso.
     * Single-use: marca usado + invalida cualquier otro token activo del user.
     */
    public function resetPassword(string $plainToken, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) return false;
        $plainToken = trim($plainToken);
        if ($plainToken === '' || strlen($plainToken) !== 64) return false;

        $token = $this->tokens->findActiveByHash(hash('sha256', $plainToken));
        if (!$token) return false;

        $user = $token->getUser();
        $user->setPassword($this->hasher->hashPassword($user, $newPassword));

        $token->markUsed();
        $this->em->flush();

        // Invalidar TODOS los demás tokens activos del usuario.
        $this->tokens->invalidateAllForUser($user);

        $this->logger->info('password reset success', ['user_id' => $user->getId()]);
        return true;
    }
}

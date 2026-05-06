<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Distingue al super-admin (m.aguilera89@gmail.com): única cuenta que puede
 * gestionar usuarios y aprobar/rechazar registros.
 */
class SuperAdminVoter extends Voter
{
    public const IS_SUPER_ADMIN = 'IS_SUPER_ADMIN';

    public function __construct(private readonly string $superAdminEmail = 'm.aguilera89@gmail.com')
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::IS_SUPER_ADMIN;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) return false;
        return strcasecmp($user->getEmail(), $this->superAdminEmail) === 0;
    }
}

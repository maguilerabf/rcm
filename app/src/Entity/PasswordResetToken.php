<?php

namespace App\Entity;

use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PasswordResetTokenRepository::class)]
#[ORM\Table(name: 'password_reset_tokens')]
class PasswordResetToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'token_hash', type: 'string', length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(name: 'expires_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'used_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'requester_ip', type: 'string', length: 45, nullable: true)]
    private ?string $requesterIp = null;

    #[ORM\Column(name: 'requester_ua', type: 'string', length: 255, nullable: true)]
    private ?string $requesterUa = null;

    public function __construct(User $user, string $tokenHash, \DateTimeImmutable $expiresAt, ?string $ip = null, ?string $ua = null)
    {
        $this->id = Uuid::v4();
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->requesterIp = $ip;
        $this->requesterUa = $ua ? mb_substr($ua, 0, 255) : null;
    }

    public function getId(): Uuid { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getTokenHash(): string { return $this->tokenHash; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getUsedAt(): ?\DateTimeImmutable { return $this->usedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getRequesterIp(): ?string { return $this->requesterIp; }

    public function isUsed(): bool { return $this->usedAt !== null; }
    public function isExpired(\DateTimeImmutable $now): bool { return $now > $this->expiresAt; }
    public function isActive(\DateTimeImmutable $now): bool { return !$this->isUsed() && !$this->isExpired($now); }

    public function markUsed(): self
    {
        $this->usedAt = new \DateTimeImmutable();
        return $this;
    }
}

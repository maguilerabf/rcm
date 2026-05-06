<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'users_email_uniq', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 120)]
    private string $firstName = '';

    #[ORM\Column(length: 120)]
    private string $lastName = '';

    #[ORM\Column]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    /** pending|active|rejected. Solo `active` puede iniciar sesión. */
    #[ORM\Column(length: 16, options: ['default' => 'active'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(name: 'approval_token', type: 'string', length: 64, nullable: true)]
    private ?string $approvalToken = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'approved_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(name: 'approved_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower(trim($email));
        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);
        return $this;
    }

    public function getDisplayName(): string
    {
        $full = trim($this->firstName . ' ' . $this->lastName);
        return $full !== '' ? $full : $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self
    {
        if (!in_array($s, [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_REJECTED], true)) {
            throw new \InvalidArgumentException("Estado inválido: {$s}");
        }
        $this->status = $s;
        return $this;
    }
    public function isActive(): bool { return $this->status === self::STATUS_ACTIVE; }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }

    public function getApprovalToken(): ?string { return $this->approvalToken; }
    public function setApprovalToken(?string $t): self { $this->approvalToken = $t; return $this; }

    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function setApprovedBy(?User $u): self { $this->approvedBy = $u; return $this; }

    public function getApprovedAt(): ?\DateTimeImmutable { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeImmutable $at): self { $this->approvedAt = $at; return $this; }
}

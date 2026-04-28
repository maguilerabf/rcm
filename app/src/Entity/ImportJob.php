<?php

namespace App\Entity;

use App\Repository\ImportJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ImportJobRepository::class)]
#[ORM\Table(name: 'import_job')]
#[ORM\Index(name: 'import_job_kind_status_idx', columns: ['kind', 'status'])]
class ImportJob
{
    public const KIND_TELESALUD = 'telesalud';
    public const KIND_INSCRITOS = 'inscritos';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 32)]
    private string $kind;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 255)]
    private string $storedPath;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $rowsTotal = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $rowsImported = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(name: 'is_active')]
    private bool $active = false;

    public function __construct(string $kind, string $originalFilename, string $storedPath)
    {
        $this->id = Uuid::v7();
        $this->kind = $kind;
        $this->originalFilename = $originalFilename;
        $this->storedPath = $storedPath;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getStoredPath(): string
    {
        return $this->storedPath;
    }

    public function getRowsTotal(): ?int
    {
        return $this->rowsTotal;
    }

    public function setRowsTotal(?int $rowsTotal): self
    {
        $this->rowsTotal = $rowsTotal;
        return $this;
    }

    public function getRowsImported(): ?int
    {
        return $this->rowsImported;
    }

    public function setRowsImported(?int $rowsImported): self
    {
        $this->rowsImported = $rowsImported;
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function markStarted(): self
    {
        $this->status = self::STATUS_RUNNING;
        $this->startedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function markDone(int $rowsImported): self
    {
        $this->status = self::STATUS_DONE;
        $this->rowsImported = $rowsImported;
        $this->finishedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->status = self::STATUS_FAILED;
        $this->error = $error;
        $this->finishedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $user): self
    {
        $this->createdBy = $user;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }
}

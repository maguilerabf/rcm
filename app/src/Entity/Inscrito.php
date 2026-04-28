<?php

namespace App\Entity;

use App\Repository\InscritoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InscritoRepository::class)]
#[ORM\Table(name: 'inscrito')]
#[ORM\Index(name: 'inscrito_run_dv_idx', columns: ['run_dv'])]
#[ORM\Index(name: 'inscrito_import_job_idx', columns: ['import_job_id'])]
class Inscrito
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: ImportJob::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ImportJob $importJob;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $establecimiento = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $run = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $dv = null;

    /**
     * UPPER(TRIM(run)) || UPPER(TRIM(dv)) — clave de cruce.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $runDv = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombres = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apellidoPaterno = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apellidoMaterno = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $sexo = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $fechaNacimiento = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $edadAnios = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $edadMeses = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $edadDias = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sector = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $estado = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $situacion = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getImportJob(): ImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(ImportJob $importJob): self
    {
        $this->importJob = $importJob;
        return $this;
    }

    public function getEstablecimiento(): ?string
    {
        return $this->establecimiento;
    }

    public function setEstablecimiento(?string $establecimiento): self
    {
        $this->establecimiento = $establecimiento;
        return $this;
    }

    public function getRun(): ?string
    {
        return $this->run;
    }

    public function setRun(?string $run): self
    {
        $this->run = $run;
        $this->recomputeRunDv();
        return $this;
    }

    public function getDv(): ?string
    {
        return $this->dv;
    }

    public function setDv(?string $dv): self
    {
        $this->dv = $dv;
        $this->recomputeRunDv();
        return $this;
    }

    public function getRunDv(): ?string
    {
        return $this->runDv;
    }

    private function recomputeRunDv(): void
    {
        if ($this->run === null && $this->dv === null) {
            $this->runDv = null;
            return;
        }
        $this->runDv = strtoupper(trim((string) $this->run) . trim((string) $this->dv));
    }

    public function getNombres(): ?string
    {
        return $this->nombres;
    }

    public function setNombres(?string $nombres): self
    {
        $this->nombres = $nombres;
        return $this;
    }

    public function getApellidoPaterno(): ?string
    {
        return $this->apellidoPaterno;
    }

    public function setApellidoPaterno(?string $apellidoPaterno): self
    {
        $this->apellidoPaterno = $apellidoPaterno;
        return $this;
    }

    public function getApellidoMaterno(): ?string
    {
        return $this->apellidoMaterno;
    }

    public function setApellidoMaterno(?string $apellidoMaterno): self
    {
        $this->apellidoMaterno = $apellidoMaterno;
        return $this;
    }

    public function getSexo(): ?string
    {
        return $this->sexo;
    }

    public function setSexo(?string $sexo): self
    {
        $this->sexo = $sexo;
        return $this;
    }

    public function getFechaNacimiento(): ?string
    {
        return $this->fechaNacimiento;
    }

    public function setFechaNacimiento(?string $fechaNacimiento): self
    {
        $this->fechaNacimiento = $fechaNacimiento;
        return $this;
    }

    public function getEdadAnios(): ?int
    {
        return $this->edadAnios;
    }

    public function setEdadAnios(?int $edadAnios): self
    {
        $this->edadAnios = $edadAnios;
        return $this;
    }

    public function getEdadMeses(): ?int
    {
        return $this->edadMeses;
    }

    public function setEdadMeses(?int $edadMeses): self
    {
        $this->edadMeses = $edadMeses;
        return $this;
    }

    public function getEdadDias(): ?int
    {
        return $this->edadDias;
    }

    public function setEdadDias(?int $edadDias): self
    {
        $this->edadDias = $edadDias;
        return $this;
    }

    public function getSector(): ?string
    {
        return $this->sector;
    }

    public function setSector(?string $sector): self
    {
        $this->sector = $sector;
        return $this;
    }

    public function getEstado(): ?string
    {
        return $this->estado;
    }

    public function setEstado(?string $estado): self
    {
        $this->estado = $estado;
        return $this;
    }

    public function getSituacion(): ?string
    {
        return $this->situacion;
    }

    public function setSituacion(?string $situacion): self
    {
        $this->situacion = $situacion;
        return $this;
    }
}

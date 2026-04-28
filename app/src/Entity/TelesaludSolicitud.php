<?php

namespace App\Entity;

use App\Repository\TelesaludSolicitudRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TelesaludSolicitudRepository::class)]
#[ORM\Table(name: 'telesalud_solicitud')]
#[ORM\Index(name: 'telesalud_identificador_norm_idx', columns: ['identificador_norm'])]
#[ORM\Index(name: 'telesalud_import_job_idx', columns: ['import_job_id'])]
class TelesaludSolicitud
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: ImportJob::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ImportJob $importJob;

    /**
     * Identificador externo (columna "ID" del CSV).
     */
    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cesfam = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $prioridad = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $codigoSeguimiento = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $informacionAdicional = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fechaSolicitud = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $genero = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apellidoPaterno = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apellidoMaterno = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombreSocial = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $tipoIdentificador = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $numIdentificador = null;

    /**
     * UPPER(TRIM(numIdentificador)) — usado para join contra padron.run_dv.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $identificadorNorm = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $edad = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $direccion = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $tipoPrestador = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motivoConsulta = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $especificidad = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $estado = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $contactado = null;

    /**
     * Resto de columnas (cierre, derivaciones 1..3, etc.) en JSON para no inflar el schema.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $extra = null;

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

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getCesfam(): ?string
    {
        return $this->cesfam;
    }

    public function setCesfam(?string $cesfam): self
    {
        $this->cesfam = $cesfam;
        return $this;
    }

    public function getPrioridad(): ?int
    {
        return $this->prioridad;
    }

    public function setPrioridad(?int $prioridad): self
    {
        $this->prioridad = $prioridad;
        return $this;
    }

    public function getCodigoSeguimiento(): ?string
    {
        return $this->codigoSeguimiento;
    }

    public function setCodigoSeguimiento(?string $codigoSeguimiento): self
    {
        $this->codigoSeguimiento = $codigoSeguimiento;
        return $this;
    }

    public function getInformacionAdicional(): ?string
    {
        return $this->informacionAdicional;
    }

    public function setInformacionAdicional(?string $informacionAdicional): self
    {
        $this->informacionAdicional = $informacionAdicional;
        return $this;
    }

    public function getFechaSolicitud(): ?\DateTimeImmutable
    {
        return $this->fechaSolicitud;
    }

    public function setFechaSolicitud(?\DateTimeImmutable $fechaSolicitud): self
    {
        $this->fechaSolicitud = $fechaSolicitud;
        return $this;
    }

    public function getGenero(): ?string
    {
        return $this->genero;
    }

    public function setGenero(?string $genero): self
    {
        $this->genero = $genero;
        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): self
    {
        $this->nombre = $nombre;
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

    public function getNombreSocial(): ?string
    {
        return $this->nombreSocial;
    }

    public function setNombreSocial(?string $nombreSocial): self
    {
        $this->nombreSocial = $nombreSocial;
        return $this;
    }

    public function getTipoIdentificador(): ?string
    {
        return $this->tipoIdentificador;
    }

    public function setTipoIdentificador(?string $tipoIdentificador): self
    {
        $this->tipoIdentificador = $tipoIdentificador !== null ? strtolower(trim($tipoIdentificador)) : null;
        return $this;
    }

    public function getNumIdentificador(): ?string
    {
        return $this->numIdentificador;
    }

    public function setNumIdentificador(?string $numIdentificador): self
    {
        $this->numIdentificador = $numIdentificador;
        $this->identificadorNorm = $numIdentificador !== null ? strtoupper(trim($numIdentificador)) : null;
        return $this;
    }

    public function getIdentificadorNorm(): ?string
    {
        return $this->identificadorNorm;
    }

    public function getEdad(): ?string
    {
        return $this->edad;
    }

    public function setEdad(?string $edad): self
    {
        $this->edad = $edad;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): self
    {
        $this->telefono = $telefono;
        return $this;
    }

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): self
    {
        $this->direccion = $direccion;
        return $this;
    }

    public function getTipoPrestador(): ?string
    {
        return $this->tipoPrestador;
    }

    public function setTipoPrestador(?string $tipoPrestador): self
    {
        $this->tipoPrestador = $tipoPrestador;
        return $this;
    }

    public function getMotivoConsulta(): ?string
    {
        return $this->motivoConsulta;
    }

    public function setMotivoConsulta(?string $motivoConsulta): self
    {
        $this->motivoConsulta = $motivoConsulta;
        return $this;
    }

    public function getEspecificidad(): ?string
    {
        return $this->especificidad;
    }

    public function setEspecificidad(?string $especificidad): self
    {
        $this->especificidad = $especificidad;
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

    public function getContactado(): ?string
    {
        return $this->contactado;
    }

    public function setContactado(?string $contactado): self
    {
        $this->contactado = $contactado;
        return $this;
    }

    public function getExtra(): ?array
    {
        return $this->extra;
    }

    public function setExtra(?array $extra): self
    {
        $this->extra = $extra;
        return $this;
    }
}

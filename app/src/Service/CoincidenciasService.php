<?php

namespace App\Service;

use App\Repository\ImportJobRepository;
use App\Entity\ImportJob;
use Doctrine\DBAL\Connection;

/**
 * Cruce SQL entre la última importación de Telesalud y la última de Inscritos.
 * Toma el último ImportJob `done` de cada tipo y hace LEFT JOIN por identificador_norm = run_dv,
 * filtrando tipo_identificador = 'run' (los pasaportes no cruzan, ver DATOS.md).
 */
class CoincidenciasService
{
    public function __construct(
        private readonly Connection $db,
        private readonly ImportJobRepository $jobs,
    ) {
    }

    /**
     * @return array{telesalud: ?ImportJob, inscritos: ?ImportJob}
     */
    public function activeJobs(): array
    {
        return [
            'telesalud' => $this->jobs->findActiveByKind(ImportJob::KIND_TELESALUD),
            'inscritos' => $this->jobs->findActiveByKind(ImportJob::KIND_INSCRITOS),
        ];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(int $page, int $perPage, ?string $search = null, ?string $sector = null): array
    {
        $jobs = $this->activeJobs();
        if (!$jobs['telesalud'] || !$jobs['inscritos']) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params, $types] = $this->buildWhere($jobs, $search, $sector);

        $count = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM telesalud_solicitud s
             INNER JOIN inscrito i ON i.run_dv = s.identificador_norm AND i.import_job_id = :inscritos_job
             {$where}",
            $params,
            $types,
        );

        $rows = $this->db->fetchAllAssociative(
            "SELECT
                s.external_id, s.cesfam, s.prioridad, s.codigo_seguimiento,
                s.fecha_solicitud, s.genero, s.nombre, s.apellido_paterno, s.apellido_materno,
                s.nombre_social, s.tipo_identificador, s.num_identificador,
                s.edad, s.email, s.telefono, s.direccion,
                s.tipo_prestador, s.motivo_consulta, s.especificidad,
                s.estado, s.contactado, s.informacion_adicional,
                i.sector, i.establecimiento AS sector_establecimiento
             FROM telesalud_solicitud s
             INNER JOIN inscrito i ON i.run_dv = s.identificador_norm AND i.import_job_id = :inscritos_job
             {$where}
             ORDER BY s.fecha_solicitud DESC NULLS LAST, s.id DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $perPage, 'offset' => $offset]),
            array_merge($types, ['limit' => \PDO::PARAM_INT, 'offset' => \PDO::PARAM_INT]),
        );

        return ['rows' => $rows, 'total' => $count];
    }

    /**
     * Itera todas las coincidencias en streaming (para exportar xlsx sin cargar todo en RAM).
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamAll(?string $sector = null): \Generator
    {
        $jobs = $this->activeJobs();
        if (!$jobs['telesalud'] || !$jobs['inscritos']) {
            return;
        }

        [$where, $params, $types] = $this->buildWhere($jobs, null, $sector);

        $stmt = $this->db->prepare(
            "SELECT
                s.external_id, s.cesfam, s.prioridad, s.codigo_seguimiento,
                s.fecha_solicitud, s.genero, s.nombre, s.apellido_paterno, s.apellido_materno,
                s.nombre_social, s.tipo_identificador, s.num_identificador,
                s.edad, s.email, s.telefono, s.direccion,
                s.tipo_prestador, s.motivo_consulta, s.especificidad,
                s.estado, s.contactado, s.informacion_adicional,
                i.sector, i.establecimiento AS sector_establecimiento
             FROM telesalud_solicitud s
             INNER JOIN inscrito i ON i.run_dv = s.identificador_norm AND i.import_job_id = :inscritos_job
             {$where}
             ORDER BY i.sector NULLS LAST, s.fecha_solicitud DESC NULLS LAST"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $types[$key] ?? \PDO::PARAM_STR);
        }

        $result = $stmt->executeQuery();
        while ($row = $result->fetchAssociative()) {
            yield $row;
        }
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function buildWhere(array $jobs, ?string $search, ?string $sector): array
    {
        $where = ' WHERE s.import_job_id = :telesalud_job AND s.tipo_identificador = :tipo';
        $params = [
            'telesalud_job' => $jobs['telesalud']->getId()->toRfc4122(),
            'inscritos_job' => $jobs['inscritos']->getId()->toRfc4122(),
            'tipo' => 'run',
        ];
        $types = [];

        if ($sector !== null && $sector !== '') {
            if ($sector === '__sin_sector__') {
                $where .= " AND (i.sector IS NULL OR i.sector = '')";
            } else {
                $where .= ' AND i.sector = :sector';
                $params['sector'] = $sector;
            }
        }
        if ($search !== null && $search !== '') {
            $where .= ' AND (s.nombre ILIKE :search OR s.apellido_paterno ILIKE :search OR s.apellido_materno ILIKE :search OR s.num_identificador ILIKE :search OR s.email ILIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        return [$where, $params, $types];
    }

    /**
     * Sectores distintos en la última carga de inscritos (para el filtro).
     *
     * @return array<int, string>
     */
    public function distinctSectores(): array
    {
        $jobs = $this->activeJobs();
        if (!$jobs['inscritos']) {
            return [];
        }
        $rows = $this->db->fetchFirstColumn(
            'SELECT DISTINCT sector FROM inscrito WHERE import_job_id = :job AND sector IS NOT NULL AND sector <> \'\' ORDER BY sector',
            ['job' => $jobs['inscritos']->getId()->toRfc4122()],
        );
        return $rows;
    }
}

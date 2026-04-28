<?php

namespace App\Service;

use App\Repository\ImportJobRepository;
use App\Entity\ImportJob;
use Doctrine\DBAL\Connection;

/**
 * Cruce SQL entre la última importación de Telesalud y la última de Inscritos.
 * LEFT JOIN: las solicitudes de Telesalud (tipo_identificador='run') que no cruzan con
 * Inscritos también aparecen — en la UI se muestran como "No inscritos".
 *
 * Además, agrupa las solicitudes por (identificador_norm, tipo_prestador) y expone
 * `repeticiones` (COUNT en la partición) para resaltar los pacientes que aparecen
 * más de una vez con el mismo prestador en el mismo reporte de Telesalud.
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
    public function paginate(int $page, int $perPage, ?string $search = null, ?string $sector = null, ?string $prestador = null): array
    {
        $jobs = $this->activeJobs();
        if (!$jobs['telesalud'] || !$jobs['inscritos']) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, $page);
        $perPage = max(1, min(500, $perPage));
        $offset = ($page - 1) * $perPage;

        [$baseSql, $baseParams, $baseTypes] = $this->buildBaseQuery($jobs);
        [$filterSql, $filterParams, $filterTypes] = $this->buildOuterFilters($search, $sector, $prestador);

        $params = array_merge($baseParams, $filterParams);
        $types = array_merge($baseTypes, $filterTypes);

        $count = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM ({$baseSql}) g WHERE g.rn = 1{$filterSql}",
            $params,
            $types,
        );

        $rows = $this->db->fetchAllAssociative(
            "SELECT * FROM ({$baseSql}) g
             WHERE g.rn = 1{$filterSql}
             ORDER BY g.fecha_solicitud DESC NULLS LAST, g.s_id DESC
             LIMIT :limit OFFSET :offset",
            array_merge($params, ['limit' => $perPage, 'offset' => $offset]),
            array_merge($types, ['limit' => \PDO::PARAM_INT, 'offset' => \PDO::PARAM_INT]),
        );

        return ['rows' => array_map([$this, 'shapeRow'], $rows), 'total' => $count];
    }

    /**
     * Itera todas las coincidencias en streaming (para exportar xlsx sin cargar todo en RAM).
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamAll(?string $sector = null, ?string $prestador = null): \Generator
    {
        $jobs = $this->activeJobs();
        if (!$jobs['telesalud'] || !$jobs['inscritos']) {
            return;
        }

        [$baseSql, $baseParams, $baseTypes] = $this->buildBaseQuery($jobs);
        [$filterSql, $filterParams, $filterTypes] = $this->buildOuterFilters(null, $sector, $prestador);

        $params = array_merge($baseParams, $filterParams);
        $types = array_merge($baseTypes, $filterTypes);

        $stmt = $this->db->prepare(
            "SELECT * FROM ({$baseSql}) g
             WHERE g.rn = 1{$filterSql}
             ORDER BY g.sector NULLS LAST, g.fecha_solicitud DESC NULLS LAST"
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $types[$key] ?? \PDO::PARAM_STR);
        }

        $result = $stmt->executeQuery();
        while ($row = $result->fetchAssociative()) {
            yield $this->shapeRow($row);
        }
    }

    /**
     * Inner SELECT con window functions para conteo y dedup por (identificador_norm, tipo_prestador).
     * `repeticiones` se calcula sobre Telesalud antes del JOIN para no inflarse si hubiera
     * registros duplicados en el padrón. La búsqueda del inscrito usa LATERAL ... LIMIT 1
     * para garantizar que cada solicitud aparezca una sola vez.
     *
     * @param array{telesalud: ImportJob, inscritos: ImportJob} $jobs
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function buildBaseQuery(array $jobs): array
    {
        $sql = "SELECT
                    s.external_id, s.cesfam, s.prioridad, s.codigo_seguimiento,
                    s.fecha_solicitud, s.genero, s.nombre, s.apellido_paterno, s.apellido_materno,
                    s.nombre_social, s.tipo_identificador, s.num_identificador, s.identificador_norm,
                    s.edad, s.email, s.telefono, s.direccion,
                    s.tipo_prestador, s.motivo_consulta, s.especificidad,
                    s.estado, s.contactado, s.informacion_adicional,
                    s.id AS s_id, s.repeticiones, s.rn,
                    i.inscrito_id, i.sector, i.sector_establecimiento
                FROM (
                    SELECT
                        external_id, cesfam, prioridad, codigo_seguimiento,
                        fecha_solicitud, genero, nombre, apellido_paterno, apellido_materno,
                        nombre_social, tipo_identificador, num_identificador, identificador_norm,
                        edad, email, telefono, direccion,
                        tipo_prestador, motivo_consulta, especificidad,
                        estado, contactado, informacion_adicional, id,
                        COUNT(*) OVER (PARTITION BY identificador_norm, COALESCE(tipo_prestador, '')) AS repeticiones,
                        ROW_NUMBER() OVER (PARTITION BY identificador_norm, COALESCE(tipo_prestador, '') ORDER BY fecha_solicitud DESC NULLS LAST, id DESC) AS rn
                    FROM telesalud_solicitud
                    WHERE import_job_id = :telesalud_job AND tipo_identificador = :tipo
                ) s
                LEFT JOIN LATERAL (
                    SELECT id AS inscrito_id, sector, establecimiento AS sector_establecimiento
                    FROM inscrito
                    WHERE run_dv = s.identificador_norm AND import_job_id = :inscritos_job
                    LIMIT 1
                ) i ON true";

        $params = [
            'telesalud_job' => $jobs['telesalud']->getId()->toRfc4122(),
            'inscritos_job' => $jobs['inscritos']->getId()->toRfc4122(),
            'tipo' => 'run',
        ];

        return [$sql, $params, []];
    }

    /**
     * Filtros aplicados sobre la tabla derivada (alias `g`). Mantienen el conteo
     * `repeticiones` calculado sobre el universo completo (sin filtros).
     *
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function buildOuterFilters(?string $search, ?string $sector, ?string $prestador = null): array
    {
        $sql = '';
        $params = [];
        $types = [];

        if ($sector !== null && $sector !== '') {
            if ($sector === '__no_inscritos__') {
                $sql .= ' AND g.inscrito_id IS NULL';
            } elseif ($sector === '__sin_sector__') {
                $sql .= " AND g.inscrito_id IS NOT NULL AND (g.sector IS NULL OR g.sector = '')";
            } else {
                $sql .= ' AND g.sector = :sector';
                $params['sector'] = $sector;
            }
        }
        if ($prestador !== null && $prestador !== '') {
            if ($prestador === '__sin_prestador__') {
                $sql .= " AND (g.tipo_prestador IS NULL OR g.tipo_prestador = '')";
            } else {
                $sql .= ' AND g.tipo_prestador = :prestador';
                $params['prestador'] = $prestador;
            }
        }
        if ($search !== null && $search !== '') {
            $sql .= ' AND (g.nombre ILIKE :search OR g.apellido_paterno ILIKE :search OR g.apellido_materno ILIKE :search OR g.num_identificador ILIKE :search OR g.email ILIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        return [$sql, $params, $types];
    }

    /**
     * Normaliza tipos: `repeticiones` y `inscrito_id` vienen como string desde DBAL.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function shapeRow(array $row): array
    {
        $row['repeticiones'] = (int) ($row['repeticiones'] ?? 1);
        $row['en_inscritos'] = !empty($row['inscrito_id']);
        unset($row['rn'], $row['s_id'], $row['inscrito_id']);
        return $row;
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

    /**
     * Prestadores distintos en la última carga de telesalud (para el filtro).
     * Filtrado a tipo_identificador='run' para alinear con el universo del cruce.
     *
     * @return array<int, string>
     */
    public function distinctPrestadores(): array
    {
        $jobs = $this->activeJobs();
        if (!$jobs['telesalud']) {
            return [];
        }
        $rows = $this->db->fetchFirstColumn(
            "SELECT DISTINCT tipo_prestador
               FROM telesalud_solicitud
              WHERE import_job_id = :job
                AND tipo_identificador = 'run'
                AND tipo_prestador IS NOT NULL
                AND tipo_prestador <> ''
              ORDER BY tipo_prestador",
            ['job' => $jobs['telesalud']->getId()->toRfc4122()],
        );
        return $rows;
    }
}

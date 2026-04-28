<?php

namespace App\Service;

use App\Entity\ImportJob;
use App\Repository\ImportJobRepository;
use Doctrine\DBAL\Connection;

/**
 * Detecta posibles duplicados dentro del padrón de inscritos:
 * personas con distinto RUN+DV pero (probablemente) la misma identidad.
 *
 * Normalización agresiva (aplicada en SQL y en PHP, alineadas):
 *   - unaccent: María → MARIA, Pérez → PEREZ
 *   - upper + trim
 *   - Y ↔ I: Yolanda ≈ Iolanda, Ynes ≈ Ines
 *
 * Tipos:
 *   - FULL    → nombres + paterno + materno + fecha exactos (post-normalización), distinto RUT
 *   - PARTIAL → paterno + materno + fecha exactos (post-normalización), distinto RUT,
 *               y los nombres comparten al menos UNA palabra normalizada (cubre
 *               "Maria" vs "Maria de los Angeles" y "Jose Bigles" vs "Bigles Jose").
 *               Si dos personas comparten apellidos+fecha pero ningún token de nombre,
 *               se separan en componentes distintos (probablemente hermanos, no duplicados).
 */
class DuplicadosInscritosService
{
    public const MATCH_FULL = 'full';
    public const MATCH_PARTIAL = 'partial';

    /** Expresión SQL para normalizar un campo de texto. */
    private const NORM = "translate(upper(trim(unaccent(coalesce(%s, '')))), 'Y', 'I')";

    private const ROW_FIELDS = [
        'id', 'run', 'dv', 'run_dv',
        'nombres', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento',
        'sector', 'estado', 'situacion', 'establecimiento',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly ImportJobRepository $jobs,
    ) {
    }

    public function activeJob(): ?ImportJob
    {
        return $this->jobs->findActiveByKind(ImportJob::KIND_INSCRITOS);
    }

    /**
     * Cuenta rápida (para badge sidebar). No hace splitting de partials.
     * @return array{fullGroups: int, partialGroupsRaw: int}
     */
    public function quickCounts(): array
    {
        $job = $this->activeJob();
        if (!$job) return ['fullGroups' => 0, 'partialGroupsRaw' => 0];
        $jobId = $job->getId()->toRfc4122();

        $nNombres = sprintf(self::NORM, 'nombres');
        $nPaterno = sprintf(self::NORM, 'apellido_paterno');
        $nMaterno = sprintf(self::NORM, 'apellido_materno');

        $where = "import_job_id = :job_id
            AND nombres IS NOT NULL AND nombres <> ''
            AND apellido_paterno IS NOT NULL AND apellido_paterno <> ''
            AND fecha_nacimiento IS NOT NULL AND fecha_nacimiento <> ''
            AND run IS NOT NULL AND dv IS NOT NULL";

        $full = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM (
                SELECT 1 FROM inscrito WHERE $where
                GROUP BY $nNombres, $nPaterno, $nMaterno, trim(fecha_nacimiento)
                HAVING COUNT(DISTINCT run_dv) > 1
            ) g",
            ['job_id' => $jobId],
        );

        $partial = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM (
                SELECT 1 FROM inscrito WHERE $where
                GROUP BY $nPaterno, $nMaterno, trim(fecha_nacimiento)
                HAVING COUNT(DISTINCT run_dv) > 1
                   AND COUNT(DISTINCT $nNombres) > 1
            ) g",
            ['job_id' => $jobId],
        );

        return ['fullGroups' => $full, 'partialGroupsRaw' => $partial];
    }

    /**
     * @param string[]|null $matchTypes  null = todos
     *
     * @return array{job: ?ImportJob, groups: array<int, array<string, mixed>>, stats: array<string, int>}
     */
    public function detect(?array $matchTypes = null, ?string $search = null): array
    {
        $job = $this->activeJob();
        if (!$job) return ['job' => null, 'groups' => [], 'stats' => []];

        $jobId = $job->getId()->toRfc4122();
        $groups = [];

        if ($matchTypes === null || in_array(self::MATCH_FULL, $matchTypes, true)) {
            $groups = array_merge($groups, $this->fullMatches($jobId, $search));
        }
        if ($matchTypes === null || in_array(self::MATCH_PARTIAL, $matchTypes, true)) {
            $groups = array_merge($groups, $this->partialMatches($jobId, $search));
        }

        $stats = [
            'totalGroups' => count($groups),
            'totalPersons' => array_sum(array_column($groups, 'rowCount')),
            'fullGroups' => count(array_filter($groups, fn ($g) => $g['matchType'] === self::MATCH_FULL)),
            'partialGroups' => count(array_filter($groups, fn ($g) => $g['matchType'] === self::MATCH_PARTIAL)),
        ];

        return ['job' => $job, 'groups' => $groups, 'stats' => $stats];
    }

    private function fullMatches(string $jobId, ?string $search): array
    {
        $nNombres = sprintf(self::NORM, 'nombres');
        $nPaterno = sprintf(self::NORM, 'apellido_paterno');
        $nMaterno = sprintf(self::NORM, 'apellido_materno');
        $nFecha   = "trim(fecha_nacimiento)";

        $sql = $this->aggregatedSql(
            keyExprs: ['nombres' => true, 'apellido_paterno' => true, 'apellido_materno' => true, 'fecha_nacimiento' => true],
            groupBy: "$nNombres, $nPaterno, $nMaterno, $nFecha",
            extraHaving: '',
            search: $search,
        );

        return $this->fetchGroups($sql, $jobId, $search, self::MATCH_FULL,
            'Mismo nombre, apellidos y fecha (normalizados)');
    }

    private function partialMatches(string $jobId, ?string $search): array
    {
        $nNombres = sprintf(self::NORM, 'nombres');
        $nPaterno = sprintf(self::NORM, 'apellido_paterno');
        $nMaterno = sprintf(self::NORM, 'apellido_materno');
        $nFecha   = "trim(fecha_nacimiento)";

        // Candidatos: comparten apellidos+fecha (normalizado) y tienen >1 RUN y >1 nombre completo distinto.
        $sql = $this->aggregatedSql(
            keyExprs: ['apellido_paterno' => true, 'apellido_materno' => true, 'fecha_nacimiento' => true],
            groupBy: "$nPaterno, $nMaterno, $nFecha",
            extraHaving: "AND COUNT(DISTINCT $nNombres) > 1",
            search: $search,
        );

        $rawGroups = $this->fetchGroups($sql, $jobId, $search, self::MATCH_PARTIAL,
            'Mismos apellidos y fecha; nombres con palabras en común');

        // Post-procesar: dentro de cada grupo, separar en componentes conexos por tokens compartidos.
        $finalGroups = [];
        foreach ($rawGroups as $g) {
            foreach ($this->splitByNameTokens($g['rows']) as $component) {
                $runs = array_unique(array_column($component, 'run_dv'));
                $nombresUnicos = array_unique(array_map(
                    fn ($r) => implode(' ', $this->normalizeName($r['nombres'])),
                    $component,
                ));
                if (count($runs) >= 2 && count($nombresUnicos) >= 2) {
                    $finalGroups[] = [
                        'matchType' => self::MATCH_PARTIAL,
                        'criterio' => $g['criterio'],
                        'key' => $g['key'],
                        'rows' => array_values($component),
                        'rowCount' => count($component),
                        'distinctRuns' => count($runs),
                    ];
                }
            }
        }

        usort($finalGroups, function ($a, $b) {
            return ($b['rowCount'] <=> $a['rowCount']) ?: ($b['distinctRuns'] <=> $a['distinctRuns']);
        });
        return array_slice($finalGroups, 0, 1000);
    }

    /**
     * Une-busca (Union-Find) sobre filas: dos filas quedan en el mismo componente si
     * sus nombres normalizados comparten al menos UNA palabra.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function splitByNameTokens(array $rows): array
    {
        $n = count($rows);
        if ($n <= 1) return [$rows];

        $tokens = array_map(fn ($r) => $this->normalizeName($r['nombres']), $rows);
        $parent = range(0, $n - 1);
        $find = function (int $x) use (&$parent) {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }
            return $x;
        };
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if (array_intersect($tokens[$i], $tokens[$j])) {
                    $ri = $find($i); $rj = $find($j);
                    if ($ri !== $rj) $parent[$ri] = $rj;
                }
            }
        }
        $byRoot = [];
        for ($i = 0; $i < $n; $i++) {
            $byRoot[$find($i)][] = $rows[$i];
        }
        return array_values($byRoot);
    }

    /**
     * Normalización idéntica a la del SQL: unaccent + upper + trim + Y→I, devuelve tokens.
     *
     * @return array<int, string>
     */
    private function normalizeName(?string $name): array
    {
        if ($name === null || $name === '') return [];
        $s = mb_strtoupper(trim($name));
        if (class_exists(\Transliterator::class)) {
            static $tr = null;
            if ($tr === null) {
                $tr = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            }
            if ($tr) $s = $tr->transliterate($s);
        } else {
            $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        }
        $s = str_replace('Y', 'I', $s);
        return array_values(array_filter(preg_split('/\s+/', $s) ?: [], static fn ($t) => $t !== ''));
    }

    /**
     * @param array<string, true> $keyExprs  columnas a incluir en el JSON `key`
     */
    private function aggregatedSql(array $keyExprs, string $groupBy, string $extraHaving, ?string $search): string
    {
        $jsonFields = [];
        foreach (self::ROW_FIELDS as $field) {
            $jsonFields[] = sprintf("'%s', %s", $field, $field);
        }
        $jsonExpr = 'JSONB_BUILD_OBJECT(' . implode(', ', $jsonFields) . ')';

        $keyJsonFields = [];
        foreach (array_keys($keyExprs) as $logical) {
            $keyJsonFields[] = sprintf("'%s', MAX(%s)", $logical, $logical);
        }
        $keyJson = 'JSONB_BUILD_OBJECT(' . implode(', ', $keyJsonFields) . ')';

        $where = "import_job_id = :job_id
            AND nombres IS NOT NULL AND nombres <> ''
            AND apellido_paterno IS NOT NULL AND apellido_paterno <> ''
            AND fecha_nacimiento IS NOT NULL AND fecha_nacimiento <> ''
            AND run IS NOT NULL AND dv IS NOT NULL";

        if ($search !== null && $search !== '') {
            $where .= " AND (
                upper(unaccent(nombres)) LIKE :search
                OR upper(unaccent(apellido_paterno)) LIKE :search
                OR upper(unaccent(coalesce(apellido_materno, ''))) LIKE :search
                OR run_dv LIKE :search
            )";
        }

        return sprintf(
            'SELECT %s AS key_json,
                    JSONB_AGG(%s ORDER BY id) AS rows_json,
                    COUNT(*) AS row_count,
                    COUNT(DISTINCT run_dv) AS distinct_runs
             FROM inscrito
             WHERE %s
             GROUP BY %s
             HAVING COUNT(DISTINCT run_dv) > 1 %s
             ORDER BY COUNT(*) DESC, COUNT(DISTINCT run_dv) DESC
             LIMIT 1500',
            $keyJson, $jsonExpr, $where, $groupBy, $extraHaving,
        );
    }

    private function fetchGroups(string $sql, string $jobId, ?string $search, string $matchType, string $criterio): array
    {
        $params = ['job_id' => $jobId];
        if ($search !== null && $search !== '') {
            $params['search'] = '%' . strtoupper($search) . '%';
        }

        $raw = $this->db->fetchAllAssociative($sql, $params);
        $groups = [];
        foreach ($raw as $row) {
            $groups[] = [
                'matchType' => $matchType,
                'criterio' => $criterio,
                'key' => json_decode($row['key_json'], true, 512, JSON_THROW_ON_ERROR),
                'rows' => json_decode($row['rows_json'], true, 512, JSON_THROW_ON_ERROR),
                'rowCount' => (int) $row['row_count'],
                'distinctRuns' => (int) $row['distinct_runs'],
            ];
        }
        return $groups;
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function streamFlat(?array $matchTypes = null, ?string $search = null): \Generator
    {
        $result = $this->detect($matchTypes, $search);
        $groupIndex = 0;
        foreach ($result['groups'] as $group) {
            $groupIndex++;
            foreach ($group['rows'] as $row) {
                yield [
                    'group_id' => $groupIndex,
                    'match_type' => $group['matchType'],
                    'criterio' => $group['criterio'],
                ] + $row;
            }
        }
    }
}

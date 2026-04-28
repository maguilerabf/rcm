<?php

namespace App\Service;

use App\Entity\ImportJob;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Lee un xlsx de "Reporte Telesalud" y lo inserta en `telesalud_solicitud`
 * usando batches de DBAL. La memoria es constante porque openspout streamea fila por fila.
 *
 * El header se detecta automáticamente buscando una fila que contenga las columnas
 * obligatorias (algunos exportadores agregan filas de metadata antes del header real).
 */
class TelesaludImporter
{
    private const BATCH_SIZE = 500;

    /**
     * Cabeceras que DEBEN estar presentes (case-insensitive, trim) para considerar
     * que una fila es el header.
     */
    private const REQUIRED_HEADERS = [
        'Nº identificador', 'Cesfam', 'Tipo identificador', 'Fecha solicitud',
    ];

    /**
     * Mapeo de cabeceras del CSV/XLSX a columnas de la tabla.
     */
    private const COLUMN_MAP = [
        'ID' => 'external_id',
        'Cesfam' => 'cesfam',
        'Prioridad' => 'prioridad',
        'Código seguimiento' => 'codigo_seguimiento',
        'Información adicional' => 'informacion_adicional',
        'Fecha solicitud' => 'fecha_solicitud',
        'Género' => 'genero',
        'Nombre' => 'nombre',
        'Apellido paterno' => 'apellido_paterno',
        'Apellido materno' => 'apellido_materno',
        'Nombre social' => 'nombre_social',
        'Tipo identificador' => 'tipo_identificador',
        'Nº identificador' => 'num_identificador',
        'Edad' => 'edad',
        'Email' => 'email',
        'Telefono' => 'telefono',
        'Dirección' => 'direccion',
        'Tipo prestador' => 'tipo_prestador',
        'Motivo consulta' => 'motivo_consulta',
        'Especificidad' => 'especificidad',
        'Estado' => 'estado',
        'Contactado' => 'contactado',
    ];

    private const EXTRA_COLUMNS = [
        'Fecha cierre', 'Tipo cierre', 'Cerrado por', 'Cargo', 'Profesión', 'Fecha agenda', 'Nota cierre',
        'Derivado a 1', 'Derivado por 1', 'Cargo 1', 'Profesión 1', 'Fecha derivación 1', 'Nota derivación 1',
        'Derivado a 2', 'Derivado por 2', 'Cargo 2', 'Profesión 2', 'Fecha derivación 2', 'Nota derivación 2',
        'Derivado a 3', 'Derivado por 3', 'Cargo 3', 'Profesión 3', 'Fecha derivación 3', 'Nota derivación 3',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly LoggerInterface $logger,
        private readonly SpoutReaderFactory $readerFactory,
        private readonly XlsxToCsvConverter $xlsxConverter,
    ) {
    }

    public function import(ImportJob $job): int
    {
        $sourcePath = $job->getStoredPath();
        $tmpCsv = null;

        if (str_ends_with(strtolower($sourcePath), '.xlsx') && $this->xlsxConverter->isAvailable()) {
            try {
                $tmpCsv = $this->xlsxConverter->convert($sourcePath, self::REQUIRED_HEADERS);
                $sourcePath = $tmpCsv;
                $this->logger->info('Telesalud: usando Python xlsx→csv', ['csv' => basename($tmpCsv)]);
            } catch (\Throwable $e) {
                $this->logger->warning('Telesalud: fallback a openspout xlsx (Python falló)', ['error' => $e->getMessage()]);
            }
        }

        $reader = $this->readerFactory->fromPath($sourcePath);
        $reader->open($sourcePath);

        $rowsImported = 0;
        $batch = [];
        $headers = null;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $this->cellsToValues($row);

                    if ($headers === null) {
                        if (HeaderDetector::matches($cells, self::REQUIRED_HEADERS)) {
                            $headers = $cells;
                            $this->logger->info('Telesalud header detected', ['cols' => count(array_filter($headers, static fn ($h) => $h !== ''))]);
                        }
                        continue;
                    }

                    if ($this->isEmptyRow($cells)) continue;

                    $assoc = [];
                    foreach ($headers as $idx => $header) {
                        if ($header === '') continue;
                        $assoc[$header] = $cells[$idx] ?? null;
                    }

                    $batch[] = $this->mapRow($job->getId()->toRfc4122(), $assoc);

                    if (count($batch) >= self::BATCH_SIZE) {
                        $rowsImported += $this->flushBatch($batch);
                        $batch = [];
                    }
                }
                break;
            }

            if ($headers === null) {
                throw new \RuntimeException('No se encontró el header. Asegúrate que el xlsx contiene las columnas: ' . implode(', ', self::REQUIRED_HEADERS));
            }

            if (!empty($batch)) {
                $rowsImported += $this->flushBatch($batch);
            }
        } finally {
            $reader->close();
            if ($tmpCsv !== null && is_file($tmpCsv)) {
                @unlink($tmpCsv);
            }
        }

        $this->logger->info('Telesalud import done', ['job' => $job->getId()->toRfc4122(), 'rows' => $rowsImported]);
        return $rowsImported;
    }

    private function cellsToValues(\OpenSpout\Common\Entity\Row $row): array
    {
        $values = [];
        foreach ($row->getCells() as $idx => $cell) {
            $v = $cell->getValue();
            if (is_string($v)) $v = trim($v);
            $values[$idx] = $v;
        }
        // densify (openspout puede dejar huecos en cells)
        $maxIdx = $values ? max(array_keys($values)) : -1;
        $dense = [];
        for ($i = 0; $i <= $maxIdx; $i++) {
            $dense[$i] = $values[$i] ?? '';
        }
        return $dense;
    }

    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $v) {
            if ($v !== null && $v !== '' && $v !== false) return false;
        }
        return true;
    }

    private function mapRow(string $jobId, array $assoc): array
    {
        $row = [
            'import_job_id' => $jobId,
            'external_id' => null,
            'cesfam' => null,
            'prioridad' => null,
            'codigo_seguimiento' => null,
            'informacion_adicional' => null,
            'fecha_solicitud' => null,
            'genero' => null,
            'nombre' => null,
            'apellido_paterno' => null,
            'apellido_materno' => null,
            'nombre_social' => null,
            'tipo_identificador' => null,
            'num_identificador' => null,
            'identificador_norm' => null,
            'edad' => null,
            'email' => null,
            'telefono' => null,
            'direccion' => null,
            'tipo_prestador' => null,
            'motivo_consulta' => null,
            'especificidad' => null,
            'estado' => null,
            'contactado' => null,
            'extra' => null,
        ];

        foreach (self::COLUMN_MAP as $header => $column) {
            $value = $assoc[$header] ?? null;
            if ($value === '') $value = null;

            if ($value !== null && $column === 'fecha_solicitud') {
                $value = $this->parseDateTime($value);
            } elseif ($value !== null && $column === 'prioridad') {
                $value = (int) $value;
            } elseif ($value !== null && $column === 'tipo_identificador') {
                $value = strtolower(trim((string) $value));
            } elseif ($value !== null && $column === 'num_identificador') {
                $value = trim((string) $value);
                $row['identificador_norm'] = strtoupper($value);
            } elseif (is_string($value)) {
                $value = trim($value);
            }

            $row[$column] = $value;
        }

        $extra = [];
        foreach (self::EXTRA_COLUMNS as $header) {
            if (array_key_exists($header, $assoc) && $assoc[$header] !== null && $assoc[$header] !== '') {
                $extra[$header] = is_string($assoc[$header]) ? trim($assoc[$header]) : $assoc[$header];
            }
        }
        $row['extra'] = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

        return $row;
    }

    private function parseDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:sP');
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:sP');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     */
    private function flushBatch(array $batch): int
    {
        $columns = array_keys($batch[0]);
        $placeholdersPerRow = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(',', array_fill(0, count($batch), $placeholdersPerRow));

        $sql = sprintf(
            'INSERT INTO telesalud_solicitud (%s) VALUES %s',
            implode(',', $columns),
            $allPlaceholders,
        );

        $params = [];
        foreach ($batch as $row) {
            foreach ($row as $value) {
                $params[] = $value;
            }
        }

        $this->db->executeStatement($sql, $params);
        return count($batch);
    }
}

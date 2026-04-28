<?php

namespace App\Service;

use App\Entity\ImportJob;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Importador del padrón de inscritos (~75k filas).
 * Sólo persiste las ~13 columnas relevantes (ver data/DATOS.md).
 *
 * Estrategia:
 *  - Auto-detecta el header (los xlsx oficiales traen ~17 filas de metadata antes).
 *  - openspout streaming, memoria constante.
 *  - Inserta vía PDO::pgsqlCopyFromArray (COPY FROM STDIN) en batches de 5000.
 *    Esto es ~10x más rápido que INSERT por lotes y bypassa overhead de prepared statements.
 */
class InscritosImporter
{
    private const BATCH_SIZE = 5000;

    private const REQUIRED_HEADERS = [
        'RUN', 'DV', 'NOMBRES', 'APELLIDO PATERNO', 'SECTOR',
    ];

    private const COLUMN_MAP = [
        'ESTABLECIMIENTO' => 'establecimiento',
        'RUN' => 'run',
        'DV' => 'dv',
        'NOMBRES' => 'nombres',
        'APELLIDO PATERNO' => 'apellido_paterno',
        'APELLIDO MATERNO' => 'apellido_materno',
        'SEXO' => 'sexo',
        'FECHA DE NACIMIENTO' => 'fecha_nacimiento',
        'EDAD AÑOS' => 'edad_anios',
        'EDAD MESES' => 'edad_meses',
        'EDAD DIAS' => 'edad_dias',
        'SECTOR' => 'sector',
        'ESTADO' => 'estado',
        'SITUACION' => 'situacion',
    ];

    /**
     * Orden EXACTO de columnas que se envían vía COPY (debe coincidir con la lista en COPY ... (col1, col2, ...)).
     */
    private const COPY_COLUMNS = [
        'import_job_id',
        'establecimiento', 'run', 'dv', 'run_dv',
        'nombres', 'apellido_paterno', 'apellido_materno',
        'sexo', 'fecha_nacimiento',
        'edad_anios', 'edad_meses', 'edad_dias',
        'sector', 'estado', 'situacion',
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

        // Si es xlsx y Python está disponible, convertimos a CSV primero (~10x más rápido para 75k filas).
        if (str_ends_with(strtolower($sourcePath), '.xlsx') && $this->xlsxConverter->isAvailable()) {
            try {
                $tmpCsv = $this->xlsxConverter->convert($sourcePath, self::REQUIRED_HEADERS);
                $sourcePath = $tmpCsv;
                $this->logger->info('Inscritos: usando Python xlsx→csv', ['csv' => basename($tmpCsv)]);
            } catch (\Throwable $e) {
                $this->logger->warning('Inscritos: fallback a openspout xlsx (Python falló)', ['error' => $e->getMessage()]);
            }
        }

        $reader = $this->readerFactory->fromPath($sourcePath);
        $reader->open($sourcePath);

        $rowsImported = 0;
        $batch = [];
        $headers = null;
        $jobId = $job->getId()->toRfc4122();

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $this->cellsToValues($row);

                    if ($headers === null) {
                        if (HeaderDetector::matches($cells, self::REQUIRED_HEADERS)) {
                            $headers = $cells;
                            $this->logger->info('Inscritos header detected', [
                                'cols' => count(array_filter($headers, static fn ($h) => $h !== '')),
                            ]);
                        }
                        continue;
                    }

                    if ($this->isEmptyRow($cells)) continue;

                    $assoc = [];
                    foreach ($headers as $idx => $header) {
                        if ($header === '') continue;
                        $assoc[$header] = $cells[$idx] ?? null;
                    }

                    $batch[] = $this->mapRow($jobId, $assoc);

                    if (count($batch) >= self::BATCH_SIZE) {
                        $rowsImported += $this->copyBatch($batch);
                        $batch = [];
                    }
                }
                break;
            }

            if ($headers === null) {
                throw new \RuntimeException('No se encontró el header del padrón. Asegúrate que el xlsx contiene las columnas: ' . implode(', ', self::REQUIRED_HEADERS));
            }

            if (!empty($batch)) {
                $rowsImported += $this->copyBatch($batch);
            }
        } finally {
            $reader->close();
            if ($tmpCsv !== null && is_file($tmpCsv)) {
                @unlink($tmpCsv);
            }
        }

        $this->logger->info('Inscritos import done', ['job' => $jobId, 'rows' => $rowsImported]);
        return $rowsImported;
    }

    /**
     * @return array<int, mixed>
     */
    private function cellsToValues(\OpenSpout\Common\Entity\Row $row): array
    {
        $values = [];
        foreach ($row->getCells() as $idx => $cell) {
            $v = $cell->getValue();
            if (is_string($v)) $v = trim($v);
            $values[$idx] = $v;
        }
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

    /**
     * @return array<string, mixed> con keys = COPY_COLUMNS
     */
    private function mapRow(string $jobId, array $assoc): array
    {
        $row = array_fill_keys(self::COPY_COLUMNS, null);
        $row['import_job_id'] = $jobId;

        foreach (self::COLUMN_MAP as $header => $column) {
            $value = $assoc[$header] ?? null;
            if ($value === '') $value = null;

            if ($value !== null && in_array($column, ['edad_anios', 'edad_meses', 'edad_dias'], true)) {
                $value = is_numeric($value) ? (int) $value : null;
            } elseif (is_string($value)) {
                $value = trim($value);
            }

            $row[$column] = $value;
        }

        if ($row['run'] !== null || $row['dv'] !== null) {
            $row['run_dv'] = strtoupper(trim((string) $row['run']) . trim((string) $row['dv']));
        }

        return $row;
    }

    /**
     * Inserta el batch usando PDO::pgsqlCopyFromArray (COPY FROM STDIN).
     * Mucho más rápido que INSERT por lotes para volúmenes grandes.
     *
     * @param array<int, array<string, mixed>> $batch
     */
    private function copyBatch(array $batch): int
    {
        $pdo = $this->db->getNativeConnection();
        if (!method_exists($pdo, 'pgsqlCopyFromArray')) {
            throw new \RuntimeException('La conexión PDO no soporta pgsqlCopyFromArray. Verifica pdo_pgsql.');
        }

        $lines = [];
        foreach ($batch as $row) {
            $line = [];
            foreach (self::COPY_COLUMNS as $col) {
                $line[] = $this->copyEscape($row[$col] ?? null);
            }
            $lines[] = implode("\t", $line);
        }

        $cols = '"' . implode('","', self::COPY_COLUMNS) . '"';
        $pdo->pgsqlCopyFromArray('inscrito', $lines, "\t", '\\N', $cols);

        return count($batch);
    }

    /**
     * Escape para formato TEXT de COPY:
     *   - null  → \N
     *   - escapar \  →  \\
     *   - \t \n \r → \t \n \r literales (escapados con backslash)
     */
    private function copyEscape(mixed $value): string
    {
        if ($value === null) return '\\N';
        $s = (string) $value;
        return strtr($s, [
            "\\" => "\\\\",
            "\t" => "\\t",
            "\n" => "\\n",
            "\r" => "\\r",
        ]);
    }
}

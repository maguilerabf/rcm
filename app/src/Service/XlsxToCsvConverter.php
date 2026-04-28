<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Convierte un xlsx a CSV via Python (openpyxl). Mucho más rápido que parsearlo en PHP.
 * Usado por los importers para volúmenes grandes (75k+ filas).
 *
 * Si Python no está disponible, lanza excepción y los importers caen al fallback PHP/openspout.
 */
class XlsxToCsvConverter
{
    /**
     * @var bool|null cache del check de disponibilidad para no re-ejecutarlo en cada import.
     */
    private ?bool $availableCache = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        // Path absoluto: si dependemos del PATH del proceso (e.g. systemd), Symfony Process
        // puede no encontrar python3 silenciosamente.
        private readonly string $pythonBin = '/usr/bin/python3',
        private readonly int $timeoutSeconds = 600,
    ) {
    }

    /**
     * Convierte $xlsxPath a un CSV temporal y devuelve el path. El caller debe borrarlo cuando termine.
     *
     * @param array<int, string> $requiredHeaders cabeceras que DEBEN estar (para auto-detectar fila del header)
     */
    public function convert(string $xlsxPath, array $requiredHeaders): string
    {
        $script = $this->projectDir . '/scripts/xlsx_to_csv.py';
        if (!is_file($script)) {
            throw new \RuntimeException("Script Python no encontrado: $script");
        }
        if (!is_file($xlsxPath)) {
            throw new \RuntimeException("Xlsx no existe: $xlsxPath");
        }

        $csvPath = sys_get_temp_dir() . '/rcm_' . bin2hex(random_bytes(6)) . '.csv';

        $process = new Process([
            $this->pythonBin,
            $script,
            $xlsxPath,
            $csvPath,
            '--required', implode(',', $requiredHeaders),
        ]);
        $process->setTimeout($this->timeoutSeconds);

        $started = microtime(true);
        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            @unlink($csvPath);
            $stderr = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new \RuntimeException("xlsx_to_csv falló: $stderr", 0, $e);
        }
        $elapsed = number_format(microtime(true) - $started, 2);

        $this->logger->info('xlsx_to_csv done', [
            'xlsx' => basename($xlsxPath),
            'csv' => basename($csvPath),
            'output' => trim($process->getOutput()),
            'elapsed' => $elapsed . 's',
        ]);

        return $csvPath;
    }

    public function isAvailable(): bool
    {
        if ($this->availableCache !== null) {
            return $this->availableCache;
        }
        try {
            $p = new Process([$this->pythonBin, '-c', 'import openpyxl; print("ok")']);
            $p->setTimeout(10);
            $p->mustRun();
            $ok = trim($p->getOutput()) === 'ok';
            $this->availableCache = $ok;
            if (!$ok) {
                $this->logger->warning('XlsxToCsvConverter: python OK pero openpyxl no responde "ok"', [
                    'python' => $this->pythonBin,
                    'output' => $p->getOutput(),
                ]);
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->logger->warning('XlsxToCsvConverter: python no disponible, fallback a openspout', [
                'python' => $this->pythonBin,
                'error' => $e->getMessage(),
            ]);
            $this->availableCache = false;
            return false;
        }
    }
}

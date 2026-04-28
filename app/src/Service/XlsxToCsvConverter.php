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
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly string $pythonBin = 'python3',
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
        try {
            $p = new Process([$this->pythonBin, '-c', 'import openpyxl; print("ok")']);
            $p->setTimeout(10);
            $p->mustRun();
            return trim($p->getOutput()) === 'ok';
        } catch (\Throwable) {
            return false;
        }
    }
}

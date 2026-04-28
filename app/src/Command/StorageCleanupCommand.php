<?php

namespace App\Command;

use App\Service\StorageCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cleanup:storage', description: 'Mide uso de almacenamiento (BD + uploads) y limpia jobs antiguos si supera el umbral.')]
class StorageCleanupCommand extends Command
{
    public function __construct(private readonly StorageCleanupService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Solo muestra el estado actual, no borra nada');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $usage = $this->service->getCurrentUsage();

        $io->title('Storage usage');
        $io->table(
            ['Métrica', 'Bytes', 'Legible'],
            [
                ['Tablas BD', $usage['dbBytes'], $this->formatBytes($usage['dbBytes'])],
                ['Uploads en disco', $usage['diskBytes'], $this->formatBytes($usage['diskBytes'])],
                ['TOTAL', $usage['totalBytes'], $this->formatBytes($usage['totalBytes'])],
                ['Umbral (cleanup si supera)', $usage['thresholdBytes'], $this->formatBytes($usage['thresholdBytes'])],
                ['Objetivo (limpiar hasta)', $usage['targetBytes'], $this->formatBytes($usage['targetBytes'])],
            ]
        );

        if ($input->getOption('dry-run')) {
            if ($usage['totalBytes'] > $usage['thresholdBytes']) {
                $io->warning('Sobre el umbral. En modo real, se borrarían jobs antiguos.');
            } else {
                $io->success('Por debajo del umbral. No se requiere cleanup.');
            }
            return Command::SUCCESS;
        }

        $result = $this->service->cleanup();

        if (!$result['triggered']) {
            $io->success('Por debajo del umbral, no se borró nada.');
            return Command::SUCCESS;
        }

        $io->section('Jobs borrados');
        $io->table(
            ['kind', 'archivo', 'filas', 'creado'],
            array_map(fn ($d) => [
                $d['kind'],
                $d['originalFilename'],
                $d['rowsImported'] ?? '-',
                $d['createdAt'],
            ], $result['deleted'])
        );

        $io->section('Estado final');
        $after = $result['usageAfter'];
        $io->writeln(sprintf(
            'Total: %s (antes %s, objetivo %s)',
            $this->formatBytes($after['totalBytes']),
            $this->formatBytes($result['usageBefore']['totalBytes']),
            $this->formatBytes($after['targetBytes']),
        ));

        if ($after['totalBytes'] > $after['targetBytes']) {
            $io->warning('Sigue por encima del objetivo. Los jobs activos / en proceso no se borran.');
        } else {
            $io->success(sprintf('Cleanup OK. Borrados: %d.', count($result['deleted'])));
        }

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1024 ** 2) return number_format($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 ** 3) return number_format($bytes / 1024 ** 2, 1) . ' MB';
        return number_format($bytes / 1024 ** 3, 2) . ' GB';
    }
}

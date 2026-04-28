<?php

namespace App\Command;

use App\Service\SpoutReaderFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:debug:xlsx', description: 'Lee las 3 primeras filas de un xlsx para debug.')]
class DebugXlsxCommand extends Command
{
    public function __construct(private readonly SpoutReaderFactory $factory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path al xlsx');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        if (!is_file($path)) {
            $output->writeln("<error>No existe: $path</error>");
            return Command::FAILURE;
        }

        $reader = $this->factory->fromPath($path);
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            $output->writeln("Sheet: " . $sheet->getName());
            foreach ($sheet->getRowIterator() as $i => $row) {
                if ($i > 25) break 2;
                $cells = $row->getCells();
                $output->writeln(sprintf("--- Row %d (cells count: %d, numCells via Row: %d) ---", $i, count($cells), $row->getNumCells()));
                foreach ($cells as $idx => $cell) {
                    $value = $cell->getValue();
                    if ($value instanceof \DateTimeInterface) $value = $value->format('Y-m-d H:i:s');
                    $valStr = is_scalar($value) ? (string) $value : json_encode($value);
                    if (mb_strlen($valStr) > 60) $valStr = mb_substr($valStr, 0, 60) . '…';
                    $output->writeln(sprintf("  [%d] (%s) = %s", $idx, $cell::class, $valStr));
                }
            }
            break;
        }

        $reader->close();
        return Command::SUCCESS;
    }
}

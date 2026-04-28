<?php

namespace App\Command;

use App\Entity\ImportJob;
use App\Repository\ImportJobRepository;
use App\Service\InscritosImporter;
use App\Service\TelesaludImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:debug:reimport', description: 'Re-procesa síncronamente un xlsx ya en disco (debug, salta la cola).')]
class ReimportFileCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImportJobRepository $jobs,
        private readonly TelesaludImporter $telesalud,
        private readonly InscritosImporter $inscritos,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('kind', InputArgument::REQUIRED, 'telesalud | inscritos')
            ->addArgument('path', InputArgument::REQUIRED, 'Path absoluto al xlsx dentro del contenedor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kind = $input->getArgument('kind');
        $path = $input->getArgument('path');

        if (!in_array($kind, [ImportJob::KIND_TELESALUD, ImportJob::KIND_INSCRITOS], true)) {
            $io->error('kind debe ser telesalud | inscritos');
            return Command::FAILURE;
        }
        if (!is_file($path)) {
            $io->error("No existe el archivo: $path");
            return Command::FAILURE;
        }

        $job = new ImportJob($kind, basename($path), $path);
        $this->em->persist($job);
        $job->markStarted();
        $this->em->flush();

        $io->info(sprintf('Procesando %s desde %s (job %s)', $kind, $path, $job->getId()->toRfc4122()));
        $start = microtime(true);

        try {
            $rows = $kind === ImportJob::KIND_TELESALUD
                ? $this->telesalud->import($job)
                : $this->inscritos->import($job);
            $job->markDone($rows);
            $this->em->flush();
            $this->jobs->activateExclusive($job);
        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());
            $this->em->flush();
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $elapsed = number_format(microtime(true) - $start, 2);
        $io->success("OK: {$rows} filas en {$elapsed}s");
        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use App\Entity\ImportJob;
use App\Repository\ImportJobRepository;
use App\Service\InscritosImporter;
use App\Service\TelesaludImporter;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:debug:bench', description: 'Mide tiempo de re-importar telesalud + inscritos.')]
class BenchmarkImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly ImportJobRepository $jobs,
        private readonly TelesaludImporter $telesalud,
        private readonly InscritosImporter $inscritos,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('telesaludPath', InputArgument::REQUIRED)
            ->addArgument('inscritosPath', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Limpiar todo para benchmark limpio
        $this->db->executeStatement('DELETE FROM inscrito');
        $this->db->executeStatement('DELETE FROM telesalud_solicitud');
        $this->db->executeStatement('DELETE FROM import_job');

        $tPath = $input->getArgument('telesaludPath');
        $iPath = $input->getArgument('inscritosPath');

        // Telesalud
        $tJob = new ImportJob(ImportJob::KIND_TELESALUD, basename($tPath), $tPath);
        $this->em->persist($tJob); $tJob->markStarted(); $this->em->flush();
        $start = microtime(true);
        $tRows = $this->telesalud->import($tJob);
        $tElapsed = microtime(true) - $start;
        $tJob->markDone($tRows); $this->em->flush();
        $this->jobs->activateExclusive($tJob);
        $io->writeln(sprintf('Telesalud:  %d filas en %.2fs (%.0f filas/s)', $tRows, $tElapsed, $tRows / $tElapsed));

        // Inscritos
        $iJob = new ImportJob(ImportJob::KIND_INSCRITOS, basename($iPath), $iPath);
        $this->em->persist($iJob); $iJob->markStarted(); $this->em->flush();
        $start = microtime(true);
        $iRows = $this->inscritos->import($iJob);
        $iElapsed = microtime(true) - $start;
        $iJob->markDone($iRows); $this->em->flush();
        $this->jobs->activateExclusive($iJob);
        $io->writeln(sprintf('Inscritos:  %d filas en %.2fs (%.0f filas/s)', $iRows, $iElapsed, $iRows / $iElapsed));

        // Coincidencias
        $coinc = (int) $this->db->fetchOne(
            "SELECT COUNT(*) FROM telesalud_solicitud s
             INNER JOIN inscrito i ON i.run_dv = s.identificador_norm
             WHERE s.tipo_identificador = 'run'"
        );
        $io->writeln(sprintf('Coincidencias: %d', $coinc));

        return Command::SUCCESS;
    }
}

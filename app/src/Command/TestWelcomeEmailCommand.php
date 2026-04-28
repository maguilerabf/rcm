<?php

namespace App\Command;

use App\Service\WelcomeMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'rcm:test:welcome-email',
    description: 'Envía un correo de bienvenida de prueba (usa el template HTML real).',
)]
final class TestWelcomeEmailCommand extends Command
{
    public function __construct(private readonly WelcomeMailer $mailer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Destinatario')
            ->addArgument('firstName', InputArgument::OPTIONAL, 'Nombre', 'Mauricio')
            ->addArgument('lastName', InputArgument::OPTIONAL, 'Apellido', 'Aguilera');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $first = $input->getArgument('firstName');
        $last  = $input->getArgument('lastName');

        $io->writeln(sprintf('Enviando welcome a <info>%s</info> (%s %s)...', $email, $first, $last));
        $this->mailer->send($email, $first, $last);
        $io->success('Welcome enviado.');

        return Command::SUCCESS;
    }
}

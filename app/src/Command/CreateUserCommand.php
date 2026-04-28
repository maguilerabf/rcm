<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Crea un usuario para login en RCM.')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email')
            ->addArgument('password', InputArgument::REQUIRED, 'Password (mínimo 8 caracteres)')
            ->addArgument('firstName', InputArgument::REQUIRED, 'Nombre')
            ->addArgument('lastName', InputArgument::REQUIRED, 'Apellido');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim((string) $input->getArgument('email')));
        $password = (string) $input->getArgument('password');
        $firstName = trim((string) $input->getArgument('firstName'));
        $lastName = trim((string) $input->getArgument('lastName'));

        if (strlen($password) < 8) {
            $io->error('El password debe tener al menos 8 caracteres.');
            return Command::FAILURE;
        }
        if ($firstName === '' || $lastName === '') {
            $io->error('Nombre y apellido son requeridos.');
            return Command::FAILURE;
        }

        if ($this->users->findOneBy(['email' => $email])) {
            $io->error("Ya existe un usuario con email {$email}.");
            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles(['ROLE_USER']);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success("Usuario {$email} ({$firstName} {$lastName}) creado.");
        return Command::SUCCESS;
    }
}

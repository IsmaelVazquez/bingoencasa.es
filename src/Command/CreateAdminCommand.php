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
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Crea un usuario administrador')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Correo del administrador')
            ->addArgument('password', InputArgument::OPTIONAL, 'Contraseña del administrador');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $email = $input->getArgument('email');
        if (!$email) {
            $emailQuestion = new Question('Email del administrador: ');
            $email = $helper->ask($input, $output, $emailQuestion);
        }

        if (!$email) {
            $output->writeln('<error>El email es obligatorio.</error>');

            return Command::FAILURE;
        }

        $existing = $this->userRepository->findOneBy(['email' => strtolower($email)]);
        if ($existing) {
            $output->writeln('<comment>Ya existe un usuario con ese email.</comment>');

            return Command::SUCCESS;
        }

        $password = $input->getArgument('password');
        if (!$password) {
            $passwordQuestion = new Question('Contraseña: ');
            $passwordQuestion->setHidden(true);
            $password = $helper->ask($input, $output, $passwordQuestion);
        }

        if (!$password) {
            $output->writeln('<error>La contraseña es obligatoria.</error>');

            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_ADMIN']);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln(sprintf('<info>Administrador %s creado correctamente.</info>', $user->getEmail()));

        return Command::SUCCESS;
    }
}

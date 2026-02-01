<?php

declare(strict_types=1);

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

#[AsCommand(
    name: 'app:user-add',
    description: 'Add new admin user'
)]
final class UserAddCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED);
        $this->addArgument('password', InputArgument::OPTIONAL);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = \trim((string) $input->getArgument('username'));

        // Check if username is empty
        if ('' === $username) {
            $io->error('Username is required');

            return Command::FAILURE;
        }

        $username = \mb_strtolower($username);

        // Check if username is valid
        if (!\filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $io->error('Username should have email format');

            return Command::FAILURE;
        }

        // Check if user already exists
        if ($this->userRepository->findOneByEmail($username)) {
            $io->error('User with this email already exists');

            return Command::FAILURE;
        }

        $user           = new User();
        $plainPassword  = $input->getArgument('password') ?? User::generatePassword(16);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            $plainPassword
        );

        $user
            ->setEmail($username)
            ->setPassword($hashedPassword)
            ->setRoles([User::ROLE_ADMIN])
        ;

        try {
            $this->em->persist($user);
            $this->em->flush();
        } catch (\Throwable $exception) {
            $io->error(\sprintf('Error: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        if (null === $input->getArgument('password')) {
            $io->success(\sprintf('User %s created successfully. Generated password: %s', $username, $plainPassword));
        } else {
            $io->success(\sprintf('User %s created successfully. Password: %s', $username, $plainPassword));
        }

        return Command::SUCCESS;
    }
}

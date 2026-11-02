<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    description: 'Add new admin user',
    name: 'app:user-add',
)]
final readonly class UserAddCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepositoryInterface $userRepository,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'User email')]
        string $username,
        #[Argument(description: 'Password (generated when omitted)')]
        ?string $password = null,
    ): int {
        $username = \mb_strtolower(\trim($username));

        if ('' === $username) {
            $io->error($this->translator->trans('command.user_add.username_required'));

            return Command::FAILURE;
        }

        if (!\filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $io->error($this->translator->trans('command.user_add.username_invalid'));

            return Command::FAILURE;
        }

        $existingUser = $this->userRepository->findOneByEmail($username);

        if ($existingUser instanceof User) {
            $io->error($this->translator->trans('command.user_add.username_exists'));

            return Command::FAILURE;
        }

        $user           = new User();
        $plainPassword  = $password ?? User::generatePassword();
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);

        $user->setEmail($username)
            ->setPassword($hashedPassword)
            ->setRoles([User::ROLE_ADMIN])
        ;

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $io->error($this->translator->trans('command.user_add.error', ['%message%' => $exception->getMessage()]));

            return Command::FAILURE;
        }

        if (null === $password) {
            $io->success($this->translator->trans('command.user_add.created_generated', [
                '%username%' => $username,
                '%password%' => $plainPassword,
            ]));
        } else {
            $io->success($this->translator->trans('command.user_add.created', [
                '%username%' => $username,
                '%password%' => $plainPassword,
            ]));
        }

        return Command::SUCCESS;
    }
}

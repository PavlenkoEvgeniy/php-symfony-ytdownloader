<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function checkPreAuth(UserInterface $user): void
    {
        $userEmail = $user->getUserIdentifier();

        $user = $this->userRepository->findOneByEmail($userEmail);

        // if user is not enabled - deny access
        if (false === $user->getIsEnabled()) {
            $message = 'Access denied';

            $this->logger->info($message, [
                'user' => $user->getEmail(),
            ]);

            throw new CustomUserMessageAccountStatusException($message);
        }
    }

    #[\Override]
    public function checkPostAuth(UserInterface $user): void
    {
    }
}

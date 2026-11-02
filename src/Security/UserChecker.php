<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoggerInterface $logger,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    #[\Override]
    public function checkPreAuth(UserInterface $user): void
    {
        $userEmail = $user->getUserIdentifier();

        $user = $this->userRepository->findOneByEmail($userEmail);

        if (!$user instanceof User || false === $user->getIsEnabled()) {
            $message = 'Access denied';

            $this->logger->info($message, [
                'user' => $user instanceof User ? $user->getEmail() : $userEmail,
            ]);

            throw new CustomUserMessageAccountStatusException($this->translator?->trans('security.account_disabled') ?? $message);
        }
    }

    #[\Override]
    public function checkPostAuth(UserInterface $user): void
    {
    }
}

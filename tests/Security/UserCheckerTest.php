<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserCheckerTest extends TestCase
{
    public function testCheckPreAuthAllowsEnabledUser(): void
    {
        $user = new User();
        $user->setEmail('enabled@example.test');
        $user->setPassword('pass');
        $user->setIsEnabled(true);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('enabled@example.test')
            ->willReturn($user);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('info');

        $checker = new UserChecker($userRepository, $logger);

        $subject = $this->createMock(UserInterface::class);
        $subject
            ->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('enabled@example.test');

        $checker->checkPreAuth($subject);
        $this->addToAssertionCount(1);
    }

    public function testCheckPreAuthDeniesDisabledUser(): void
    {
        $user = new User();
        $user->setEmail('disabled@example.test');
        $user->setPassword('pass');
        $user->setIsEnabled(false);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects($this->once())
            ->method('findOneByEmail')
            ->with('disabled@example.test')
            ->willReturn($user);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Access denied', ['user' => 'disabled@example.test']);

        $checker = new UserChecker($userRepository, $logger);

        $subject = $this->createMock(UserInterface::class);
        $subject
            ->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('disabled@example.test');

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Access denied');

        $checker->checkPreAuth($subject);
    }
}

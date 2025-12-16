<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UserAddCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private UserRepository $userRepository;

    public function setUp(): void
    {
        $kernel = static::bootKernel();
        $app    = new Application($kernel);

        $command             = $app->find('app:user:add');
        $this->commandTester = new CommandTester($command);

        $this->userRepository = $this->getContainer()->get(UserRepository::class);
    }

    public function testUserAddExecute(): void
    {
        $this->commandTester->execute([
            'username' => 'command-app-user-add-test@test.local',
            'password' => '123456',
        ]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('[OK] User command-app-user-add-test@test.local:123456 created successfully', $output);

        $newUser = $this->userRepository->findOneByEmail('command-app-user-add-test@test.local');
        $this->assertNotNull($newUser);
        $this->assertStringContainsString('command-app-user-add-test@test.local', $newUser->getEmail());
    }
}

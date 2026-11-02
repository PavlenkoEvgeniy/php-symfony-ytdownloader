<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserAddCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    /** @var string[] */
    private array $createdEmails = [];

    public function setUp(): void
    {
        $kernel = static::bootKernel();
        $app    = new Application($kernel);

        $command             = $app->find('app:user-add');
        $this->commandTester = new CommandTester($command);

        $this->userRepository = $this->getContainer()->get(UserRepository::class);
        $this->entityManager  = $this->getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->isOpen()) {
            foreach ($this->createdEmails as $email) {
                $user = $this->userRepository->findOneByEmail($email);
                if ($user instanceof User) {
                    $this->entityManager->remove($user);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    /**
     * Verifies the command creates a user with the given email and password.
     */
    public function testUserAddExecute(): void
    {
        $this->createdEmails[] = 'command-app-user-add-test@test.local';

        $this->commandTester->execute([
            'username' => 'command-app-user-add-test@test.local',
            'password' => '123456',
        ]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('[OK] User command-app-user-add-test@test.local created successfully', $output);

        $newUser = $this->userRepository->findOneByEmail('command-app-user-add-test@test.local');
        $this->assertNotNull($newUser);
        $this->assertStringContainsString('command-app-user-add-test@test.local', $newUser->getEmail());
    }

    /**
     * Verifies an empty username is rejected.
     */
    public function testEmptyUsernameIsRejected(): void
    {
        $this->commandTester->execute(['username' => '   ']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Username is required', $this->commandTester->getDisplay());
    }

    /**
     * Verifies a username without email format is rejected.
     */
    public function testInvalidUsernameIsRejected(): void
    {
        $this->commandTester->execute(['username' => 'not-an-email']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Username should have email format', $this->commandTester->getDisplay());
    }

    /**
     * Verifies an existing username is rejected.
     */
    public function testExistingUsernameIsRejected(): void
    {
        $this->commandTester->execute(['username' => 'admin@admin.local']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('User with this email already exists', $this->commandTester->getDisplay());
    }

    /**
     * Verifies a password is generated when it is omitted.
     */
    public function testPasswordIsGeneratedWhenOmitted(): void
    {
        $email                 = 'command-generated-password@test.local';
        $this->createdEmails[] = $email;

        $this->commandTester->execute(['username' => $email]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Generated', $this->commandTester->getDisplay());
        $this->assertNotNull($this->userRepository->findOneByEmail($email));
    }

    /**
     * Verifies a persistence failure is reported when the database rejects the user.
     */
    public function testPersistenceFailureIsReported(): void
    {
        $email = \sprintf(
            '%s@%s.%s.local',
            \str_repeat('a', 64),
            \str_repeat('b', 63),
            \str_repeat('c', 63),
        );

        $this->commandTester->execute(['username' => $email, 'password' => 'password']);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Error:', $this->commandTester->getDisplay());
    }
}

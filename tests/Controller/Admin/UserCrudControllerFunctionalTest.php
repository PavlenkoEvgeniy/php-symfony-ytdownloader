<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudControllerFunctionalTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    /** @var string[] */
    private array $createdEmails      = [];
    private ?string $uploadDir        = null;
    private int $uploadDirPermissions = 0;

    public function setUp(): void
    {
        $this->client         = static::createClient();
        $this->entityManager  = $this->getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = $this->getContainer()->get(UserRepository::class);
        $this->passwordHasher = $this->getContainer()->get(UserPasswordHasherInterface::class);

        // EasyAdmin needs a writable avatar dir; BypassFinals breaks is_writable() in tests.
        // 0777 is restored in tearDown().
        $this->uploadDir            = \sprintf('%s/public/uploads/avatars', (string) $this->getContainer()->getParameter('kernel.project_dir'));
        $this->uploadDirPermissions = (int) (\fileperms($this->uploadDir) & 0777);

        if (\is_dir($this->uploadDir)) {
            \chmod($this->uploadDir, 0777);
            \clearstatcache(true, $this->uploadDir);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdEmails as $email) {
            $user = $this->userRepository->findOneByEmail($email);
            if ($user instanceof User) {
                $this->entityManager->remove($user);
            }
        }

        $this->entityManager->flush();

        if (null !== $this->uploadDir && \is_dir($this->uploadDir)) {
            \chmod($this->uploadDir, $this->uploadDirPermissions);
            \clearstatcache(true, $this->uploadDir);
        }

        parent::tearDown();
    }

    /**
     * Verifies a user can be created through the admin panel and gets a hashed password.
     */
    public function testCreateUserHashesPassword(): void
    {
        $email                 = \sprintf('crud-create-%s@test.local', \bin2hex(\random_bytes(4)));
        $this->createdEmails[] = $email;

        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/user/new');
        $this->assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="User[_token]"]')->attr('value');

        $this->client->request(Request::METHOD_POST, '/admin/user/new', [
            'User' => [
                'email'     => $email,
                'password'  => [
                    'first'  => 'Password123!',
                    'second' => 'Password123!',
                ],
                'roles'     => ['ROLE_USER'],
                'isEnabled' => '1',
                '_token'    => $token,
            ],
        ]);

        $this->assertResponseRedirects();

        $this->entityManager->clear();
        $user = $this->userRepository->findOneByEmail($email);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($this->passwordHasher->isPasswordValid($user, 'Password123!'));
    }

    /**
     * Verifies editing a user through the admin panel hashes a new password.
     */
    public function testEditUserHashesPassword(): void
    {
        $email                 = \sprintf('crud-edit-%s@test.local', \bin2hex(\random_bytes(4)));
        $this->createdEmails[] = $email;

        $user = new User();

        $user->setEmail($email)
            ->setPassword($this->passwordHasher->hashPassword($user, 'OldPassword123!'))
            ->setRoles(['ROLE_USER'])
        ;
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, \sprintf('/admin/user/%d/edit', $user->getId()));
        $this->assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="User[_token]"]')->attr('value');

        $this->client->request(Request::METHOD_POST, \sprintf('/admin/user/%d/edit', $user->getId()), [
            'User' => [
                'email'     => $email,
                'password'  => 'NewPassword123!',
                'roles'     => ['ROLE_USER'],
                'isEnabled' => '1',
                '_token'    => $token,
            ],
        ]);

        $this->assertResponseRedirects();

        $this->entityManager->clear();
        $updated = $this->userRepository->findOneByEmail($email);

        $this->assertInstanceOf(User::class, $updated);
        $this->assertTrue($this->passwordHasher->isPasswordValid($updated, 'NewPassword123!'));
    }

    private function loginAsAdmin(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);
    }
}

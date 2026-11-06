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

    /**
     * Verifies clicking a user in the list opens the user card.
     */
    public function testIndexRowLinksToUserCard(): void
    {
        $email                 = \sprintf('crud-row-%s@test.local', \bin2hex(\random_bytes(4)));
        $this->createdEmails[] = $email;

        $user = new User();

        $user->setEmail($email)
            ->setPassword($this->passwordHasher->hashPassword($user, 'Password123!'))
            ->setRoles(['ROLE_USER'])
        ;
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/user');
        $this->assertResponseIsSuccessful();

        $row = $crawler->filter(\sprintf('tr[data-id="%d"]', $user->getId()));
        $this->assertCount(1, $row);

        $url = (string) $row->attr('data-default-action-url');

        $this->assertStringContainsString(\sprintf('/admin/user/%d', $user->getId()), $url);
        $this->assertStringNotContainsString('/edit', $url);
    }

    /**
     * Verifies the users list shows the Telegram link status as Yes or No.
     */
    public function testIndexShowsTelegramLinkStatus(): void
    {
        $linkedEmail   = \sprintf('crud-list-linked-%s@test.local', \bin2hex(\random_bytes(4)));
        $unlinkedEmail = \sprintf('crud-list-unlinked-%s@test.local', \bin2hex(\random_bytes(4)));

        $this->createdEmails[] = $linkedEmail;
        $this->createdEmails[] = $unlinkedEmail;

        $linked = new User();

        $linked->setEmail($linkedEmail)
            ->setPassword($this->passwordHasher->hashPassword($linked, 'Password123!'))
            ->setRoles(['ROLE_USER'])
            ->setTelegramUserId(12_345)
            ->setTelegramUsername('telegram_user')
        ;

        $unlinked = new User();

        $unlinked->setEmail($unlinkedEmail)
            ->setPassword($this->passwordHasher->hashPassword($unlinked, 'Password123!'))
            ->setRoles(['ROLE_USER'])
        ;

        $this->entityManager->persist($linked);
        $this->entityManager->persist($unlinked);
        $this->entityManager->flush();

        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/user');
        $this->assertResponseIsSuccessful();

        $linkedCell = $crawler
            ->filter(\sprintf('tr[data-id="%d"] td[data-column="telegramLinked"]', $linked->getId()))
            ->first()
            ->text()
        ;
        $unlinkedCell = $crawler
            ->filter(\sprintf('tr[data-id="%d"] td[data-column="telegramLinked"]', $unlinked->getId()))
            ->first()
            ->text()
        ;

        $this->assertSame('Yes', \trim($linkedCell));
        $this->assertSame('No', \trim($unlinkedCell));
    }

    /**
     * Verifies the inline Enabled toggle asks for confirmation before changing.
     */
    public function testEnabledToggleAsksForConfirmation(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/user');
        $this->assertResponseIsSuccessful();

        $toggle = $crawler->filter('td[data-column="isEnabled"] input[type="checkbox"]')->first();

        $this->assertCount(1, $toggle);
        $this->assertStringContainsString('confirm(', (string) $toggle->attr('onclick'));
    }

    /**
     * Verifies the admin user card shows the Telegram link status.
     */
    public function testDetailPageShowsTelegramLinkStatus(): void
    {
        $email                 = \sprintf('crud-telegram-%s@test.local', \bin2hex(\random_bytes(4)));
        $this->createdEmails[] = $email;

        $user = new User();

        $user->setEmail($email)
            ->setPassword($this->passwordHasher->hashPassword($user, 'Password123!'))
            ->setRoles(['ROLE_USER'])
            ->setTelegramUserId(12_345)
            ->setTelegramUsername('telegram_user')
        ;
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->loginAsAdmin();

        $this->client->request(Request::METHOD_GET, \sprintf('/admin/user/%d', $user->getId()));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Linked as @telegram_user');
    }

    private function loginAsAdmin(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Controller\Telegram;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TelegramClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class LinkControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private string $token;

    #[\Override]
    public function setUp(): void
    {
        $this->client          = static::createClient();
        $this->userRepository  = $this->getContainer()->get(UserRepository::class);
        $this->entityManager   = $this->getContainer()->get(EntityManagerInterface::class);
        $this->token           = \bin2hex(\random_bytes(16));
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->cache()->deleteItem(\sprintf('telegram_link_%s', $this->token));

        $user = $this->userRepository->findOneByEmail('user@test.local');

        if ($user instanceof User && $user->isTelegramLinked()) {
            $user->unlinkTelegram();
        }

        $created = $this->userRepository->findOneByEmail('telegram-owner@test.local');

        if ($created instanceof User) {
            $this->entityManager->remove($created);
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    /**
     * Verifies the linking page is reserved for authenticated users.
     */
    public function testLinkPageRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, \sprintf('/telegram/link?token=%s', $this->token));

        $this->assertResponseRedirects();
    }

    /**
     * Verifies an unknown or used token renders the expired error page.
     */
    public function testUnknownTokenRendersTheExpiredError(): void
    {
        $this->loginAs('user@test.local');

        $this->client->request(Request::METHOD_GET, \sprintf('/telegram/link?token=%s', $this->token));

        $this->assertResponseStatusCodeSame(410);
        $this->assertSelectorTextContains('.alert-danger', 'This link has expired or was already used.');
    }

    /**
     * Verifies a valid token renders the confirmation form for the linked account.
     */
    public function testValidTokenRendersTheConfirmationForm(): void
    {
        $this->storeLinkToken(['telegram_user_id' => 777, 'telegram_username' => 'jane']);
        $this->loginAs('user@test.local');

        $this->client->request(Request::METHOD_GET, \sprintf('/telegram/link?token=%s', $this->token));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.text-muted-soft', '@jane');
        $this->assertSelectorTextContains('.text-muted-soft', 'user@test.local');
        $this->assertSelectorExists('button[type="submit"]');
    }

    /**
     * Verifies a Telegram account without a username is labeled by its numeric id.
     */
    public function testAccountWithoutUsernameIsLabeledByItsId(): void
    {
        $this->storeLinkToken(['telegram_user_id' => 777, 'telegram_username' => null]);
        $this->loginAs('user@test.local');

        $this->client->request(Request::METHOD_GET, \sprintf('/telegram/link?token=%s', $this->token));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.text-muted-soft', 'Telegram user 777');
    }

    /**
     * Verifies a token can be re-opened by the account that already owns the link.
     */
    public function testTokenOfTheOwnLinkStillRendersTheForm(): void
    {
        $user = $this->linkUser(777);
        $this->storeLinkToken(['telegram_user_id' => 777, 'telegram_username' => 'jane']);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, \sprintf('/telegram/link?token=%s', $this->token));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button[type="submit"]');
    }

    /**
     * Verifies a Telegram account owned by another user cannot be claimed.
     */
    public function testTelegramAccountOfAnotherUserIsRejected(): void
    {
        $this->createTelegramOwner(777);
        $this->storeLinkToken(['telegram_user_id' => 777, 'telegram_username' => 'jane']);
        $this->loginAs('user@test.local');

        $this->client->request(Request::METHOD_GET, \sprintf('/telegram/link?token=%s', $this->token));

        $this->assertResponseStatusCodeSame(410);
        $this->assertSelectorTextContains('.alert-danger', 'already linked to another user');
    }

    /**
     * Verifies an account already linked to another Telegram account must unlink first.
     */
    public function testAccountLinkedToAnotherTelegramAccountIsRejected(): void
    {
        $this->linkUser(888);
        $this->storeLinkToken(['telegram_user_id' => 777, 'telegram_username' => 'jane']);
        $this->loginAs('user@test.local');

        $this->client->request(Request::METHOD_GET, \sprintf('/telegram/link?token=%s', $this->token));

        $this->assertResponseStatusCodeSame(410);
        $this->assertSelectorTextContains('.alert-danger', 'Unlink it first');
    }

    /**
     * Verifies a confirmation without a valid CSRF token is refused.
     */
    public function testConfirmationWithInvalidCsrfIsRefused(): void
    {
        $this->storeLinkToken(['telegram_user_id' => 777, 'telegram_username' => 'jane']);
        $this->loginAs('user@test.local');

        $this->client->request(Request::METHOD_POST, \sprintf('/telegram/link?token=%s', $this->token), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/ui/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'The form has expired. Please try again.');

        $this->assertFalse((bool) $this->currentUser()?->getTelegramUserId());
        $this->assertTrue($this->cache()->hasItem(\sprintf('telegram_link_%s', $this->token)));
    }

    /**
     * Verifies a confirmation stores the link, drops the token and tells the bot.
     */
    public function testConfirmationLinksTheAccount(): void
    {
        $this->storeLinkToken(['telegram_user_id' => 777, 'telegram_username' => 'jane']);
        $this->loginAs('user@test.local');

        $this->client->disableReboot();

        $telegramClient = $this->createMock(TelegramClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->with(777, $this->stringContains('Linked to user@test.local'));

        $this->getContainer()->set(TelegramClientInterface::class, $telegramClient);

        $crawler = $this->client->request(Request::METHOD_GET, \sprintf('/telegram/link?token=%s', $this->token));
        $this->client->submit($crawler->selectButton('Link account')->form());

        $this->assertResponseRedirects('/ui/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Telegram account linked.');

        $user = $this->currentUser();
        $this->assertNotNull($user);
        $this->assertSame(777, $user->getTelegramUserId());
        $this->assertSame('jane', $user->getTelegramUsername());
        $this->assertNotNull($user->getTelegramLinkedAt());

        $this->assertFalse($this->cache()->hasItem(\sprintf('telegram_link_%s', $this->token)));
    }

    /**
     * Verifies a failing bot notification does not break the linking confirmation.
     */
    public function testConfirmationSucceedsWhenTheBotMessageFails(): void
    {
        $this->storeLinkToken(['telegram_user_id' => 777, 'telegram_username' => null]);
        $this->loginAs('user@test.local');

        $this->client->disableReboot();

        $telegramClient = $this->createStub(TelegramClientInterface::class);
        $telegramClient->method('sendMessage')->willThrowException(new \RuntimeException('bot is down'));

        $this->getContainer()->set(TelegramClientInterface::class, $telegramClient);

        $crawler = $this->client->request(Request::METHOD_GET, \sprintf('/telegram/link?token=%s', $this->token));
        $this->client->submit($crawler->selectButton('Link account')->form());

        $this->assertResponseRedirects('/ui/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Telegram account linked.');

        $this->assertSame(777, $this->currentUser()?->getTelegramUserId());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function storeLinkToken(array $data): void
    {
        $item = $this->cache()->getItem(\sprintf('telegram_link_%s', $this->token));
        $item->set($data);
        $this->cache()->save($item);
    }

    private function loginAs(string $email): void
    {
        $user = $this->userRepository->findOneByEmail($email);
        $this->assertNotNull($user);

        $this->client->loginUser($user);
    }

    private function linkUser(int $telegramUserId): User
    {
        $user = $this->userRepository->findOneByEmail('user@test.local');
        $this->assertNotNull($user);

        $user->setTelegramUserId($telegramUserId)
            ->setTelegramUsername('jane')
            ->setTelegramLinkedAt(new \DateTimeImmutable())
        ;
        $this->entityManager->flush();

        return $user;
    }

    private function createTelegramOwner(int $telegramUserId): void
    {
        $owner = new User();

        $owner->setEmail('telegram-owner@test.local')
            ->setPassword('irrelevant-hash')
            ->setIsEnabled(true)
            ->setTelegramUserId($telegramUserId)
        ;

        $this->entityManager->persist($owner);
        $this->entityManager->flush();
    }

    private function currentUser(): ?User
    {
        return $this->userRepository->findOneByEmail('user@test.local');
    }

    private function cache(): CacheItemPoolInterface
    {
        /** @var CacheItemPoolInterface $pool */
        $pool = $this->getContainer()->get('cache.app');

        return $pool;
    }
}

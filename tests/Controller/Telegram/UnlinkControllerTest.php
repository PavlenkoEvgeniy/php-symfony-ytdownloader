<?php

declare(strict_types=1);

namespace App\Tests\Controller\Telegram;

use App\Entity\DownloadTask;
use App\Entity\User;
use App\Repository\DownloadTaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;

final class UnlinkControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private UserRepository $userRepository;
    private DownloadTaskRepository $downloadTaskRepository;
    private EntityManagerInterface $entityManager;
    /** @var int[] */
    private array $createdTaskIds = [];

    #[\Override]
    public function setUp(): void
    {
        $this->client                 = static::createClient();
        $this->userRepository         = $this->getContainer()->get(UserRepository::class);
        $this->downloadTaskRepository = $this->getContainer()->get(DownloadTaskRepository::class);
        $this->entityManager          = $this->getContainer()->get(EntityManagerInterface::class);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->cache()->deleteItem('telegram_pending_777');

        foreach ($this->createdTaskIds as $taskId) {
            $task = $this->downloadTaskRepository->find($taskId);

            if ($task instanceof DownloadTask) {
                $this->entityManager->remove($task);
            }
        }

        $user = $this->userRepository->findOneByEmail('user@test.local');

        if ($user instanceof User && $user->isTelegramLinked()) {
            $user->unlinkTelegram();
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    /**
     * Verifies the unlink action is reserved for authenticated users.
     */
    public function testUnlinkRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_POST, '/telegram/unlink');

        $this->assertResponseRedirects();
    }

    /**
     * Verifies an invalid CSRF token keeps the link in place.
     */
    public function testUnlinkWithInvalidCsrfIsRefused(): void
    {
        $this->client->loginUser($this->linkUser());

        $this->client->request(Request::METHOD_POST, '/telegram/unlink', ['_token' => 'invalid']);

        $this->assertResponseRedirects('/ui/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'The form has expired. Please try again.');

        $this->assertTrue((bool) $this->currentUser()?->getTelegramUserId());
    }

    /**
     * Verifies an account that is not linked anymore still gets the confirmation flash.
     */
    public function testUnlinkWithoutALinkStillConfirms(): void
    {
        $user = $this->linkUser();
        $this->client->loginUser($user);

        $token = $this->extractToken($this->client->request(Request::METHOD_GET, '/ui/profile'));

        $this->client->request(Request::METHOD_POST, '/telegram/unlink', ['_token' => $token]);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Telegram account unlinked.');

        $this->client->request(Request::METHOD_POST, '/telegram/unlink', ['_token' => $token]);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Telegram account unlinked.');

        $this->assertNull($this->currentUser()?->getTelegramUserId());
    }

    /**
     * Verifies unlinking clears the account, the pending selection and queued downloads.
     */
    public function testUnlinkClearsTheAccountAndQueuedDownloads(): void
    {
        $user = $this->linkUser();

        $queued     = $this->createTask($user, DownloadTask::STATUS_QUEUED);
        $processing = $this->createTask($user, DownloadTask::STATUS_PROCESSING);

        $this->cache()->save($this->cache()->getItem('telegram_pending_777')->set(['token' => 'a']));

        $this->client->loginUser($user);
        $this->client->submit($this->unlinkForm());

        $this->assertResponseRedirects('/ui/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Telegram account unlinked.');

        $user = $this->currentUser();
        $this->assertNull($user?->getTelegramUserId());
        $this->assertNull($user?->getTelegramUsername());
        $this->assertNull($user?->getTelegramLinkedAt());

        $this->assertFalse($this->cache()->hasItem('telegram_pending_777'));
        $this->assertNull($this->downloadTaskRepository->find($queued));
        $this->assertNotNull($this->downloadTaskRepository->find($processing));
    }

    private function linkUser(): User
    {
        $user = $this->userRepository->findOneByEmail('user@test.local');
        $this->assertNotNull($user);

        $user->setTelegramUserId(777)
            ->setTelegramUsername('jane')
            ->setTelegramLinkedAt(new \DateTimeImmutable())
        ;
        $this->entityManager->flush();

        return $user;
    }

    private function createTask(User $user, string $status): int
    {
        $task = new DownloadTask();

        $task->setUrl(\sprintf('https://test.local/unlink-%s', $status))->setQuality('best')
            ->setStatus($status)
            ->setUser($user)
        ;

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->createdTaskIds[] = (int) $task->getId();

        return (int) $task->getId();
    }

    private function unlinkForm(): Form
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/ui/profile');

        return $crawler->selectButton('Unlink')->form();
    }

    private function extractToken(Crawler $crawler): string
    {
        $input = $crawler->filter('form[action="/telegram/unlink"] input[name="_token"]');
        $this->assertCount(1, $input, 'CSRF token input for the unlink form was not found.');

        return (string) $input->attr('value');
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

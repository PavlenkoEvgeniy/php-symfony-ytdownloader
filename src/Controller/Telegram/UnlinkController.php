<?php

declare(strict_types=1);

namespace App\Controller\Telegram;

use App\Entity\User;
use App\Repository\DownloadTaskRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route('/telegram/unlink', name: 'telegram_unlink', methods: [Request::METHOD_POST])]
final class UnlinkController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DownloadTaskRepositoryInterface $downloadTaskRepository,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('telegram_unlink', $request->request->getString('_token'))) {
            $this->addFlash('error', $this->translator->trans('telegram.profile.invalid_csrf'));

            return $this->redirectToRoute('ui_profile_index');
        }

        if ($user->isTelegramLinked()) {
            $telegramUserId = $user->getTelegramUserIdOrFail();

            $this->cache->deleteItem('telegram_pending_' . $telegramUserId);
            $this->downloadTaskRepository->deleteQueuedByUser($user);
            $user->unlinkTelegram();
            $this->entityManager->flush();
        }

        $this->addFlash('success', $this->translator->trans('telegram.profile.unlinked'));

        return $this->redirectToRoute('ui_profile_index');
    }
}

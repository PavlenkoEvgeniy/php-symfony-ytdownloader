<?php

declare(strict_types=1);

namespace App\Controller\Telegram;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;
use App\Service\TelegramClientInterface;
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
#[Route('/telegram/link', name: 'telegram_link', methods: [Request::METHOD_GET, Request::METHOD_POST])]
final class LinkController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly UserRepositoryInterface $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TelegramClientInterface $telegramClient,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, #[CurrentUser] User $user): Response
    {
        $token = $request->query->getString('token');
        $item  = $this->cache->getItem('telegram_link_' . $token);

        /** @var array<string, mixed>|null $data */
        $data = $item->isHit() ? $item->get() : null;

        if (!\is_array($data) || !isset($data['telegram_user_id'])) {
            return $this->renderError('telegram.link.expired');
        }

        $telegramUserId = (int) $data['telegram_user_id'];
        $telegramName   = \is_string($data['telegram_username'] ?? null) ? $data['telegram_username'] : null;
        $account        = null === $telegramName ? \sprintf('Telegram user %d', $telegramUserId) : '@' . $telegramName;

        $owner = $this->userRepository->findOneByTelegramUserId($telegramUserId);

        if ($owner instanceof User && $owner->getId() !== $user->getId()) {
            return $this->renderError('telegram.link.telegram_already_linked', $account);
        }

        if ($user->isTelegramLinked() && $user->getTelegramUserId() !== $telegramUserId) {
            return $this->renderError('telegram.link.account_already_linked', $account);
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            if (!$this->isCsrfTokenValid('telegram_link', $request->request->getString('_token'))) {
                $this->addFlash('error', $this->translator->trans('telegram.link.invalid_csrf'));

                return $this->redirectToRoute('ui_profile_index');
            }

            $user->setTelegramUserId($telegramUserId)
                ->setTelegramUsername($telegramName)
                ->setTelegramLinkedAt(new \DateTimeImmutable())
            ;
            $this->entityManager->flush();
            $this->cache->deleteItem('telegram_link_' . $token);

            try {
                $this->telegramClient->sendMessage($telegramUserId, \sprintf('Linked to %s.', $user->getEmail()));
            } catch (\Throwable) {
                // The web page is the source of truth; the bot message is best effort.
            }

            $this->addFlash('success', $this->translator->trans('telegram.link.success'));

            return $this->redirectToRoute('ui_profile_index');
        }

        return $this->render('telegram/link.html.twig', [
            'token'   => $token,
            'account' => $account,
            'email'   => $user->getEmail(),
            'error'   => null,
        ]);
    }

    private function renderError(string $key, ?string $account = null): Response
    {
        return $this->render('telegram/link.html.twig', [
            'token'   => null,
            'account' => $account,
            'email'   => null,
            'error'   => $this->translator->trans($key),
        ], new Response(status: Response::HTTP_GONE));
    }
}

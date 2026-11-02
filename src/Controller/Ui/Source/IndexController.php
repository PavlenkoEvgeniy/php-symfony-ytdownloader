<?php

declare(strict_types=1);

namespace App\Controller\Ui\Source;

use App\Repository\SourceRepositoryInterface;
use App\Service\QueueStatsServiceInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ui/source', name: 'ui_source_index', methods: [Request::METHOD_GET])]
final class IndexController extends AbstractController
{
    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly QueueStatsServiceInterface $queueStatsService,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    public function __invoke(
        Request $request,
        #[MapQueryParameter]
        string $order = 'desc',
    ): Response {
        $files = $this->sourceRepository->findAllOrderedByCreatedAt($order);

        $perPage = 10;

        $pagination = $this->paginator->paginate(
            $files,
            \max($request->query->getInt('page', 1), 1),
            $perPage
        );

        $currentPage    = $pagination->getCurrentPageNumber();
        $startingNumber = ($currentPage - 1) * $perPage + 1;

        return $this->render('ui/source/index.html.twig', [
            'pagination'     => $pagination,
            'order'          => $order,
            'startingNumber' => $startingNumber,
            'stats'          => $this->queueStatsService->getStats(),
        ]);
    }
}

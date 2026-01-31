<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Source;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class SourceController extends AbstractController
{
    #[Route(path: '/api/v1/source', name: 'api_v1_source_index', methods: [Request::METHOD_GET])]
    public function index(Request $request, SourceRepository $sourceRepository): JsonResponse
    {
        $order = \strtolower($request->query->getString('order', 'desc'));
        $order = \in_array($order, ['asc', 'desc'], true) ? $order : 'desc';

        $sources = $sourceRepository->findBy([], ['createdAt' => $order]);

        $items = [];
        foreach ($sources as $source) {
            $items[] = [
                'id' => $source->getId(),
                'filename' => $source->getFilename(),
                'filepath' => $source->getFilepath(),
                'size' => $source->getSize(),
                'created_at' => method_exists($source, 'getCreatedAt') && $source->getCreatedAt()
                    ? $source->getCreatedAt()->format(DATE_ATOM)
                    : null,
                'download_url' => $this->generateUrl('api_v1_source_download', ['id' => $source->getId()]),
            ];
        }

        return $this->json([
            'items' => $items,
            'count' => \count($items),
        ]);
    }

    #[Route(path: '/api/v1/source/{id}/download', name: 'api_v1_source_download', methods: [Request::METHOD_GET])]
    public function download(Source $source, LoggerInterface $logger): Response
    {
        $filePath = $source->getFilepath() . '/' . $source->getFilename();

        if (!\file_exists($filePath)) {
            $logger->alert('File not found for API download', [
                'message' => \sprintf('File not found, not possible to download file: %s', $filePath),
                'sourceId' => $source->getId(),
            ]);

            return $this->json(['message' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $source->getFilename()
        );

        return $response;
    }

    #[Route(path: '/api/v1/source/{id}', name: 'api_v1_source_delete', methods: [Request::METHOD_DELETE])]
    public function delete(Source $source, EntityManagerInterface $em, LoggerInterface $logger): JsonResponse
    {
        $filePath = $source->getFilepath() . '/' . $source->getFilename();

        if (!\file_exists($filePath)) {
            $logger->alert('File not found for API delete', [
                'message' => \sprintf('File not found, not possible to delete file: %s', $filePath),
                'sourceId' => $source->getId(),
            ]);

            return $this->json(['message' => 'File not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            \unlink($filePath);
        } catch (\Throwable $e) {
            $logger->alert('Error deleting file via API', [
                'message' => $e->getMessage(),
                'sourceId' => $source->getId(),
                'filepath' => $filePath,
            ]);

            return $this->json(['message' => 'An error occurred while deleting the file.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $em->remove($source);
        $em->flush();

        return $this->json(['message' => 'File deleted.'], Response::HTTP_OK);
    }
}

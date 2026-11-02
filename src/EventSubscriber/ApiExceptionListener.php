<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Dto\ApiProblem;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Serializer\SerializerInterface;

#[AsEventListener]
final readonly class ApiExceptionListener
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!\str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $flattenException = FlattenException::createFromThrowable($event->getThrowable());
        $status           = $flattenException->getStatusCode();
        $title            = Response::$statusTexts[$status] ?? 'Error';
        $detail           = $status >= 500 || '' === $flattenException->getMessage()
            ? $title
            : $flattenException->getMessage();

        $problem = new ApiProblem(
            type: 'about:blank',
            title: $title,
            status: $status,
            detail: $detail,
            instance: $event->getRequest()->getPathInfo(),
        );

        $event->setResponse(JsonResponse::fromJsonString(
            $this->serializer->serialize($problem, 'json'),
            $status,
            ['Content-Type' => 'application/problem+json'],
        ));
    }
}

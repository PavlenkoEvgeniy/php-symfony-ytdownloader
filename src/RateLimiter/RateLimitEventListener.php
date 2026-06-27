<?php

declare(strict_types=1);

namespace App\RateLimiter;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RateLimitEventListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $factory,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $controller = $event->getController();

        if (!\is_array($controller)) {
            return;
        }

        [$object, $method] = $controller;
        $reflection        = new \ReflectionClass($object);
        $reflectionMethod  = $reflection->getMethod($method);

        // Check method-level attribute first, then class-level
        $attributes = $reflectionMethod->getAttributes(RateLimitAttribute::class);
        if (0 === \count($attributes)) {
            $attributes = $reflection->getAttributes(RateLimitAttribute::class);
        }

        if (0 === \count($attributes)) {
            return;
        }

        /** @var RateLimitAttribute $attribute */
        $attribute = $attributes[0]->newInstance();

        $request = $event->getRequest();
        $key     = $this->getClientKey($request);
        $limiter = $this->factory->create($key);
        $limit   = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $response = new Response('Rate limit exceeded.', Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('RateLimit-Limit', (string) $attribute->getLimit());
            $response->headers->set('RateLimit-Remaining', (string) $limit->getRemainingTokens());
            $response->headers->set('RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
            $event->setController(static fn () => $response);
        }
    }

    private function getClientKey(Request $request): string
    {
        // Use authenticated user ID if available, otherwise use IP
        if ($user = $request->attributes->get('user')) {
            return 'rate_limit_api_' . $user;
        }

        return 'rate_limit_api_' . ($request->getClientIp() ?? 'unknown');
    }
}

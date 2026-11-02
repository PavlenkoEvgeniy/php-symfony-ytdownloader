<?php

declare(strict_types=1);

namespace App\RateLimiter;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener]
final readonly class RateLimitEventListener
{
    public function __construct(
        #[Autowire(service: 'limiter.api')]
        private RateLimiterFactory $rateLimiterFactory,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
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
        $limiter = $this->rateLimiterFactory->create($key);
        $limit   = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $response = new Response(
                $this->translator->trans('error.rate_limit_exceeded'),
                Response::HTTP_TOO_MANY_REQUESTS,
            );
            $response->headers->set('RateLimit-Limit', (string) $attribute->getLimit());
            $response->headers->set('RateLimit-Remaining', (string) $limit->getRemainingTokens());
            $response->headers->set('RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
            $event->setController(static fn () => $response);
        }
    }

    private function getClientKey(Request $request): string
    {
        $user = $request->attributes->get('user');
        $key  = $user instanceof UserInterface ? $user->getUserIdentifier() : ($request->getClientIp() ?? 'unknown');

        return \sprintf('rate_limit_api_%s', $key);
    }
}

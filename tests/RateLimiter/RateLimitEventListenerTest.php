<?php

declare(strict_types=1);

namespace App\Tests\RateLimiter;

use App\Entity\User;
use App\RateLimiter\RateLimitAttribute;
use App\RateLimiter\RateLimitEventListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RateLimitEventListenerTest extends TestCase
{
    private RateLimitEventListener $listener;

    protected function setUp(): void
    {
        $this->listener = $this->createListener(60);
    }

    /**
     * Verifies the listener leaves controllers of sub-requests unchanged.
     */
    public function testIgnoresSubRequests(): void
    {
        $request = new Request();
        $kernel  = $this->createStub(HttpKernelInterface::class);
        $event   = new ControllerEvent($kernel, function () {}, $request, HttpKernelInterface::SUB_REQUEST);

        $controller = $event->getController();
        ($this->listener)($event);
        $this->assertSame($controller, $event->getController());
    }

    /**
     * Verifies the listener ignores controllers that are plain closures.
     */
    public function testIgnoresCallableWithoutClass(): void
    {
        $callable = function () {};
        $request  = new Request();
        $kernel   = $this->createStub(HttpKernelInterface::class);
        $event    = new ControllerEvent($kernel, $callable, $request, HttpKernelInterface::MAIN_REQUEST);

        ($this->listener)($event);
        $this->assertSame($callable, $event->getController());
    }

    /**
     * Verifies the listener ignores controllers without the attribute.
     */
    public function testIgnoresControllerWithoutRateLimitAttribute(): void
    {
        $controller = new class {
            public function method(): void
            {
            }
        };

        $request = new Request();
        $kernel  = $this->createStub(HttpKernelInterface::class);
        $event   = new ControllerEvent($kernel, [$controller, 'method'], $request, HttpKernelInterface::MAIN_REQUEST);

        $originalController = $event->getController();
        ($this->listener)($event);
        $this->assertSame($originalController, $event->getController());
    }

    /**
     * Verifies the client IP is used as the limiter key and the request is blocked.
     */
    public function testEnforcesRateLimitWithClientIp(): void
    {
        $listener   = $this->createListener(1);
        $controller = new #[RateLimitAttribute(limit: 1)] class {
            #[RateLimitAttribute(limit: 1, interval: 60)]
            public function method(): Response
            {
                return new Response('OK');
            }
        };

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event  = new ControllerEvent($kernel, [$controller, 'method'], $request, HttpKernelInterface::MAIN_REQUEST);

        $listener($event);
        $this->assertSame([$controller, 'method'], $event->getController());

        $listener($event);
        $response = ($event->getController())();

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertSame('1', $response->headers->get('RateLimit-Limit'));
    }

    /**
     * Verifies different authenticated users get independent limiter buckets.
     */
    public function testUsesUserIdAsKeyWhenAvailable(): void
    {
        $listener   = $this->createListener(1);
        $controller = new #[RateLimitAttribute(limit: 1)] class {
            #[RateLimitAttribute(limit: 1)]
            public function method(): Response
            {
                return new Response('OK');
            }
        };
        $kernel = $this->createStub(HttpKernelInterface::class);

        $firstUser  = new User()->setEmail('first@test.local');
        $secondUser = new User()->setEmail('second@test.local');

        $firstRequest = new Request();
        $firstRequest->attributes->set('user', $firstUser);
        $firstEvent = new ControllerEvent($kernel, [$controller, 'method'], $firstRequest, HttpKernelInterface::MAIN_REQUEST);

        $secondRequest = new Request();
        $secondRequest->attributes->set('user', $secondUser);
        $secondEvent = new ControllerEvent($kernel, [$controller, 'method'], $secondRequest, HttpKernelInterface::MAIN_REQUEST);

        $listener($firstEvent);
        $listener($secondEvent);

        $this->assertSame([$controller, 'method'], $firstEvent->getController());
        $this->assertSame([$controller, 'method'], $secondEvent->getController());
    }

    /**
     * Verifies the method-level attribute wins over the class-level one.
     */
    public function testPrefersMethodAttributeOverClassAttribute(): void
    {
        $listener   = $this->createListener(1);
        $controller = new #[RateLimitAttribute(limit: 100)] class {
            #[RateLimitAttribute(limit: 50)]
            public function method(): Response
            {
                return new Response('OK');
            }
        };

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event  = new ControllerEvent($kernel, [$controller, 'method'], $request, HttpKernelInterface::MAIN_REQUEST);

        $listener($event);
        $listener($event);
        $response = ($event->getController())();

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertSame('50', $response->headers->get('RateLimit-Limit'));
    }

    private function createListener(int $limit): RateLimitEventListener
    {
        $factory = new RateLimiterFactory([
            'id'       => 'api',
            'policy'   => 'sliding_window',
            'limit'    => $limit,
            'interval' => '1 minute',
        ], new InMemoryStorage());

        return new RateLimitEventListener($factory, $this->createStub(TranslatorInterface::class));
    }
}

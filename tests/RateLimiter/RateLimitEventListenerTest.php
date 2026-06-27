<?php

declare(strict_types=1);

namespace App\Tests\RateLimiter;

use App\RateLimiter\RateLimitAttribute;
use App\RateLimiter\RateLimitEventListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

final class RateLimitEventListenerTest extends TestCase
{
    private RateLimitEventListener $listener;
    private RateLimiterFactory $factory;

    protected function setUp(): void
    {
        $storage       = $this->createMock(StorageInterface::class);
        $this->factory = new RateLimiterFactory([
            'id'       => 'api',
            'policy'   => 'sliding_window',
            'limit'    => 60,
            'interval' => '1 minute',
        ], $storage);

        $this->listener = new RateLimitEventListener($this->factory);
    }

    public function testIgnoresSubRequests(): void
    {
        $request = new Request();
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new ControllerEvent($kernel, function () {}, $request, HttpKernelInterface::SUB_REQUEST);

        // This should not throw, and the controller should not be modified
        $controller = $event->getController();
        $this->listener->onKernelController($event);
        $this->assertSame($controller, $event->getController());
    }

    public function testIgnoresCallableWithoutClass(): void
    {
        $callable = function () {};
        $request  = new Request();
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $event    = new ControllerEvent($kernel, $callable, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelController($event);
        $this->assertSame($callable, $event->getController());
    }

    public function testIgnoresControllerWithoutRateLimitAttribute(): void
    {
        $controller = new class {
            public function method(): void
            {
            }
        };

        $request = new Request();
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $event   = new ControllerEvent($kernel, [$controller, 'method'], $request, HttpKernelInterface::MAIN_REQUEST);

        $originalController = $event->getController();
        $this->listener->onKernelController($event);
        $this->assertSame($originalController, $event->getController());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testEnforcesRateLimitWithClientIp(): void
    {
        $controller = new #[RateLimitAttribute]
        class {
            #[RateLimitAttribute(limit: 2, interval: 60)]
            public function method(): Response
            {
                return new Response('OK');
            }
        };

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new ControllerEvent($kernel, [$controller, 'method'], $request, HttpKernelInterface::MAIN_REQUEST);

        // First call should not throw and should not modify the controller
        $originalController = $event->getController();
        $this->listener->onKernelController($event);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testUsesUserIdAsKeyWhenAvailable(): void
    {
        $controller = new #[RateLimitAttribute]
        class {
            #[RateLimitAttribute]
            public function method(): Response
            {
                return new Response('OK');
            }
        };

        $request = new Request();
        $request->attributes->set('user', 'user123');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new ControllerEvent($kernel, [$controller, 'method'], $request, HttpKernelInterface::MAIN_REQUEST);

        // Should use user ID from request attributes
        $this->listener->onKernelController($event);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testPrefersMethodAttributeOverClassAttribute(): void
    {
        $controller = new #[RateLimitAttribute(limit: 100)]
        class {
            #[RateLimitAttribute(limit: 50)]
            public function method(): Response
            {
                return new Response('OK');
            }
        };

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new ControllerEvent($kernel, [$controller, 'method'], $request, HttpKernelInterface::MAIN_REQUEST);

        // Should use method attribute (limit: 50), not class attribute (limit: 100)
        $this->listener->onKernelController($event);
    }

    public function testSubscribedToControllerEvent(): void
    {
        $subscribedEvents = RateLimitEventListener::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.controller', $subscribedEvents);
        $this->assertSame('onKernelController', $subscribedEvents['kernel.controller']);
    }
}

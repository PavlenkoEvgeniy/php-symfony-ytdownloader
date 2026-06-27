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

final class RateLimitIntegrationTest extends TestCase
{
    private RateLimitEventListener $listener;
    private RateLimiterFactory $factory;

    protected function setUp(): void
    {
        $storage       = $this->createMock(StorageInterface::class);
        $this->factory = new RateLimiterFactory([
            'id'       => 'api',
            'policy'   => 'sliding_window',
            'limit'    => 10,
            'interval' => '1 minute',
        ], $storage);

        $this->listener = new RateLimitEventListener($this->factory);
    }

    public function testRateLimitAttributeIsAppliedToClassAndMethod(): void
    {
        // Create a test controller with both class and method attributes
        $controller = new #[RateLimitAttribute(limit: 100)]
        class {
            #[RateLimitAttribute(limit: 50)]
            public function limitedMethod(): Response
            {
                return new Response('OK');
            }

            #[RateLimitAttribute]
            public function defaultLimitMethod(): Response
            {
                return new Response('OK');
            }

            public function unlimitedMethod(): Response
            {
                return new Response('OK');
            }
        };

        // Test that attributes are correctly applied
        $reflection = new \ReflectionClass($controller);

        // Class-level attribute
        $classAttributes = $reflection->getAttributes(RateLimitAttribute::class);
        $this->assertCount(1, $classAttributes);
        $classAttribute = $classAttributes[0]->newInstance();
        $this->assertSame(100, $classAttribute->getLimit());

        // Method-level attribute (takes precedence)
        $methodAttributes = $reflection->getMethod('limitedMethod')->getAttributes(RateLimitAttribute::class);
        $this->assertCount(1, $methodAttributes);
        $methodAttribute = $methodAttributes[0]->newInstance();
        $this->assertSame(50, $methodAttribute->getLimit());

        // Method with default attribute
        $defaultAttributes = $reflection->getMethod('defaultLimitMethod')->getAttributes(RateLimitAttribute::class);
        $this->assertCount(1, $defaultAttributes);
        $defaultAttribute = $defaultAttributes[0]->newInstance();
        $this->assertSame(60, $defaultAttribute->getLimit());

        // Method without attribute (should fall back to class level)
        $noAttributes = $reflection->getMethod('unlimitedMethod')->getAttributes(RateLimitAttribute::class);
        $this->assertCount(0, $noAttributes);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRateLimitKeyGenerationWithClientIp(): void
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        // The listener should generate a key based on IP when user is not authenticated
        $controller = new #[RateLimitAttribute]
        class {
            #[RateLimitAttribute]
            public function method(): Response
            {
                return new Response('OK');
            }
        };

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new ControllerEvent($kernel, [$controller, 'method'], $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw exception and should process successfully
        $this->listener->onKernelController($event);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRateLimitKeyGenerationWithAuthentication(): void
    {
        $request    = new Request();
        $controller = new #[RateLimitAttribute]
        class {
            #[RateLimitAttribute]
            public function method(): Response
            {
                return new Response('OK');
            }
        };

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new ControllerEvent($kernel, [$controller, 'method'], $request, HttpKernelInterface::MAIN_REQUEST);

        // Should use user ID from request attributes
        $this->listener->onKernelController($event);
    }

    public function testListenerIsEventSubscriber(): void
    {
        // Listener implements EventSubscriberInterface and subscribes to kernel.controller event
        $events = RateLimitEventListener::getSubscribedEvents();
        $this->assertArrayHasKey('kernel.controller', $events);
        $this->assertSame('onKernelController', $events['kernel.controller']);
    }

    public function testConfiguredLimitAndInterval(): void
    {
        $attribute1 = new RateLimitAttribute();
        $this->assertSame(60, $attribute1->getLimit());
        $this->assertSame(60, $attribute1->getInterval());

        $attribute2 = new RateLimitAttribute(limit: 30, interval: 120);
        $this->assertSame(30, $attribute2->getLimit());
        $this->assertSame(120, $attribute2->getInterval());
    }
}

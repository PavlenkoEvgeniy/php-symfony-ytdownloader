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
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RateLimitIntegrationTest extends TestCase
{
    /**
     * Verifies class and method attributes expose their configured limits.
     */
    public function testRateLimitAttributeIsAppliedToClassAndMethod(): void
    {
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

        $reflection = new \ReflectionClass($controller);

        $classAttributes = $reflection->getAttributes(RateLimitAttribute::class);
        $this->assertCount(1, $classAttributes);
        $this->assertSame(100, $classAttributes[0]->newInstance()->getLimit());

        $methodAttributes = $reflection->getMethod('limitedMethod')->getAttributes(RateLimitAttribute::class);
        $this->assertCount(1, $methodAttributes);
        $this->assertSame(50, $methodAttributes[0]->newInstance()->getLimit());

        $defaultAttributes = $reflection->getMethod('defaultLimitMethod')->getAttributes(RateLimitAttribute::class);
        $this->assertCount(1, $defaultAttributes);
        $this->assertSame(60, $defaultAttributes[0]->newInstance()->getLimit());

        $noAttributes = $reflection->getMethod('unlimitedMethod')->getAttributes(RateLimitAttribute::class);
        $this->assertCount(0, $noAttributes);
    }

    /**
     * Verifies different client IPs consume independent rate-limit buckets.
     */
    public function testDifferentClientAddressesUseIndependentKeys(): void
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

        $firstRequest = new Request();
        $firstRequest->server->set('REMOTE_ADDR', '192.168.1.100');
        $firstEvent = new ControllerEvent($kernel, [$controller, 'method'], $firstRequest, HttpKernelInterface::MAIN_REQUEST);

        $secondRequest = new Request();
        $secondRequest->server->set('REMOTE_ADDR', '192.168.1.101');
        $secondEvent = new ControllerEvent($kernel, [$controller, 'method'], $secondRequest, HttpKernelInterface::MAIN_REQUEST);

        $listener($firstEvent);
        $listener($secondEvent);

        $this->assertSame([$controller, 'method'], $firstEvent->getController());
        $this->assertSame([$controller, 'method'], $secondEvent->getController());
    }

    /**
     * Verifies the attribute defaults and custom limit/interval settings.
     */
    public function testConfiguredLimitAndInterval(): void
    {
        $defaultAttribute = new RateLimitAttribute();
        $this->assertSame(60, $defaultAttribute->getLimit());
        $this->assertSame(60, $defaultAttribute->getInterval());

        $customAttribute = new RateLimitAttribute(limit: 30, interval: 120);
        $this->assertSame(30, $customAttribute->getLimit());
        $this->assertSame(120, $customAttribute->getInterval());
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

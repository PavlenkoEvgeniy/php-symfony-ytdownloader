<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class AuditRequestIdListener
{
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
    public function onRequest(RequestEvent $event): void
    {
        $request   = $event->getRequest();
        $requestId = $request->headers->get('X-Request-Id') ?? \bin2hex(\random_bytes(8));

        $request->attributes->set('_audit_request_id', $requestId);
    }
}

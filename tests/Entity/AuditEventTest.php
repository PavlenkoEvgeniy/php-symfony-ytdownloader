<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AuditEvent;
use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Enum\AuditChannel;
use PHPUnit\Framework\TestCase;

final class AuditEventTest extends TestCase
{
    /**
     * Verifies a minimal event exposes its required values and null defaults.
     */
    public function testMinimalEvent(): void
    {
        $occurredAt = new \DateTimeImmutable('2026-11-02 10:00:00');
        $event      = new AuditEvent(
            occurredAt: $occurredAt,
            action: AuditAction::LOGIN,
            channel: AuditChannel::UI,
            actorType: AuditActorType::USER,
            success: true,
        );

        $this->assertNull($event->getId());
        $this->assertSame($occurredAt, $event->getOccurredAt());
        $this->assertSame('auth.login', $event->getAction());
        $this->assertSame('ui', $event->getChannel());
        $this->assertSame('user', $event->getActorType());
        $this->assertTrue($event->isSuccess());
        $this->assertNull($event->getActorId());
        $this->assertNull($event->getActorLabel());
        $this->assertNull($event->getTargetType());
        $this->assertNull($event->getTargetId());
        $this->assertNull($event->getTargetLabel());
        $this->assertNull($event->getIp());
        $this->assertNull($event->getUserAgent());
        $this->assertNull($event->getRequestId());
        $this->assertNull($event->getMetadata());
        $this->assertNull($event->getMetadataJson());
    }

    /**
     * Verifies every optional value is returned unchanged.
     */
    public function testFullEvent(): void
    {
        $occurredAt = new \DateTimeImmutable('2026-11-02 10:00:00');
        $event      = new AuditEvent(
            occurredAt: $occurredAt,
            action: AuditAction::SOURCE_DELETE,
            channel: AuditChannel::API_V1,
            actorType: AuditActorType::USER,
            success: false,
            actorId: 15,
            actorLabel: 'admin@test.local',
            targetType: 'source',
            targetId: 42,
            targetLabel: 'video.mp4',
            ip: '192.0.2.1',
            userAgent: 'PHPUnit',
            requestId: 'request-1',
            metadata: ['reason' => 'file_not_found'],
        );

        $this->assertSame(15, $event->getActorId());
        $this->assertSame('admin@test.local', $event->getActorLabel());
        $this->assertSame('source', $event->getTargetType());
        $this->assertSame(42, $event->getTargetId());
        $this->assertSame('video.mp4', $event->getTargetLabel());
        $this->assertSame('192.0.2.1', $event->getIp());
        $this->assertSame('PHPUnit', $event->getUserAgent());
        $this->assertSame('request-1', $event->getRequestId());
        $this->assertSame(['reason' => 'file_not_found'], $event->getMetadata());
        $this->assertStringContainsString('"reason": "file_not_found"', (string) $event->getMetadataJson());
        $this->assertFalse($event->isSuccess());
    }
}

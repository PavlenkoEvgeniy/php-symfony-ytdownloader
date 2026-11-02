<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\AuditEventCrudController;
use App\Entity\AuditEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use PHPUnit\Framework\TestCase;

final class AuditEventCrudControllerTest extends TestCase
{
    /**
     * Verifies the CRUD controller is bound to the audit event entity.
     */
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(AuditEvent::class, AuditEventCrudController::getEntityFqcn());
    }

    /**
     * Verifies the audit log is read-only.
     */
    public function testConfigureActionsDisablesChangingEvents(): void
    {
        $actions  = (new AuditEventCrudController())->configureActions(Actions::new());
        $disabled = $actions->getAsDto(Crud::PAGE_INDEX)->getDisabledActions();

        $this->assertContains(Action::NEW, $disabled);
        $this->assertContains(Action::EDIT, $disabled);
        $this->assertContains(Action::DELETE, $disabled);
    }

    /**
     * Verifies events are sorted newest first and paginated.
     */
    public function testConfigureCrud(): void
    {
        $dto = (new AuditEventCrudController())->configureCrud(Crud::new())->getAsDto();

        $this->assertSame(['occurredAt' => 'DESC'], $dto->getDefaultSort());
        $this->assertSame(50, $dto->getPaginator()->getPageSize());
    }

    /**
     * Verifies every filter is registered.
     */
    public function testConfigureFilters(): void
    {
        $filters = (new AuditEventCrudController())->configureFilters(Filters::new())->getAsDto();

        foreach (['action', 'channel', 'success', 'actorLabel', 'targetLabel', 'ip', 'occurredAt'] as $property) {
            $this->assertNotNull($filters->getFilter($property), $property);
        }
    }

    /**
     * Verifies the index fields render actions, channels, actors and targets as text.
     */
    public function testConfigureFieldsFormatValues(): void
    {
        $fields = \iterator_to_array((new AuditEventCrudController())->configureFields(Crud::PAGE_INDEX));

        $actionFormat = $this->formatCallable($this->field($fields, 'action'));
        $this->assertSame('admin.audit.action.auth.login', $actionFormat('auth.login'));

        $channelFormat = $this->formatCallable($this->field($fields, 'channel'));
        $this->assertSame('admin.audit.channel.ui', $channelFormat('ui'));

        $actorFormat = $this->formatCallable($this->field($fields, 'actorLabel'));
        $this->assertSame('-', $actorFormat(null));
        $this->assertSame('admin@test.local', $actorFormat('admin@test.local'));

        $targetFormat = $this->formatCallable($this->field($fields, 'targetLabel'));
        $this->assertSame('-', $targetFormat(null));
        $this->assertSame('video.mp4', $targetFormat('video.mp4'));
    }

    /**
     * @param array<int, object> $fields
     */
    private function field(array $fields, string $property): object
    {
        foreach ($fields as $field) {
            $reflection = new \ReflectionObject($field);
            $dto        = $reflection->getProperty('dto')->getValue($field);

            if ($dto->getProperty() === $property) {
                return $field;
            }
        }

        $this->fail(\sprintf('Field "%s" was not found.', $property));
    }

    private function formatCallable(object $field): callable
    {
        $reflection = new \ReflectionObject($field);
        $dto        = $reflection->getProperty('dto')->getValue($field);
        $callable   = $dto->getFormatValueCallable();

        $this->assertIsCallable($callable);

        return $callable;
    }
}

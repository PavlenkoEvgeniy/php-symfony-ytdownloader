<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\LogCrudController;
use App\Entity\Log;
use App\Helper\Helper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use PHPUnit\Framework\TestCase;

final class LogCrudControllerTest extends TestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(Log::class, LogCrudController::getEntityFqcn());
    }

    public function testConfigureActionsDisablesNewAndDelete(): void
    {
        $controller = new LogCrudController();

        $actions  = Actions::new();
        $returned = $controller->configureActions($actions);

        $dto      = $returned->getAsDto(Crud::PAGE_INDEX);
        $disabled = $dto->getDisabledActions();

        $this->assertContains(Action::NEW, $disabled);
        $this->assertContains(Action::DELETE, $disabled);
        // disabling DELETE also disables BATCH_DELETE
        $this->assertContains(Action::BATCH_DELETE, $disabled);
    }

    public function testConfigureCrudSetsDefaultSort(): void
    {
        $controller = new LogCrudController();

        $crud     = Crud::new();
        $returned = $controller->configureCrud($crud);

        // reflect into private dto to assert default sort
        $ref  = new \ReflectionObject($returned);
        $prop = $ref->getProperty('dto');
        $prop->setAccessible(true);
        $dto = $prop->getValue($returned);

        $this->assertSame(['id' => 'DESC'], $dto->getDefaultSort());
    }

    public function testConfigureFields(): void
    {
        $controller = new LogCrudController();

        $fields = \iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));

        $this->assertCount(5, $fields);

        // id
        $idField = $fields[0];
        $this->assertSame('id', $this->getFieldProperty($idField));

        // type
        $typeField = $fields[1];
        $this->assertSame('type', $this->getFieldProperty($typeField));
        $this->assertStringContainsString('Result', (string) $this->getFieldLabel($typeField));

        // message
        $messageField = $fields[2];
        $this->assertSame('message', $this->getFieldProperty($messageField));

        // size -> NumberField with format callable
        $sizeField = $fields[3];
        $this->assertInstanceOf(NumberField::class, $sizeField);
        $this->assertSame('size', $this->getFieldProperty($sizeField));
        $formatCallable = $this->getFieldFormatCallable($sizeField);

        $this->assertIsCallable($formatCallable);
        $this->assertSame('-', $formatCallable(null));
        $this->assertSame(Helper::formatBytes(1024), $formatCallable(1024));

        // createdAt
        $createdAtField = $fields[4];
        $this->assertSame('createdAt', $this->getFieldProperty($createdAtField));
    }

    /**
     * Use reflection to access the private DTO to get the property name.
     */
    private function getFieldProperty(object $field): string
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $prop->setAccessible(true);
        $dto = $prop->getValue($field);

        return $dto->getProperty();
    }

    private function getFieldLabel(object $field): mixed
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $prop->setAccessible(true);
        $dto = $prop->getValue($field);

        return $dto->getLabel();
    }

    private function getFieldFormatCallable(object $field): ?callable
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $prop->setAccessible(true);
        $dto = $prop->getValue($field);

        return $dto->getFormatValueCallable();
    }
}

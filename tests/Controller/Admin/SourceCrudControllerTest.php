<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\SourceCrudController;
use App\Entity\Source;
use App\Helper\Helper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use PHPUnit\Framework\TestCase;

final class SourceCrudControllerTest extends TestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(Source::class, SourceCrudController::getEntityFqcn());
    }

    public function testConfigureActionsDisablesNewEditAndDelete(): void
    {
        $controller = new SourceCrudController();

        $actions  = Actions::new();
        $returned = $controller->configureActions($actions);

        $dto      = $returned->getAsDto(Crud::PAGE_INDEX);
        $disabled = $dto->getDisabledActions();

        $this->assertContains(Action::NEW, $disabled);
        $this->assertContains(Action::EDIT, $disabled);
        $this->assertContains(Action::DELETE, $disabled);
        // disabling DELETE also disables BATCH_DELETE
        $this->assertContains(Action::BATCH_DELETE, $disabled);
    }

    public function testConfigureFields(): void
    {
        $controller = new SourceCrudController();

        $fields = \iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));

        $this->assertCount(4, $fields);

        // id
        $idField = $fields[0];
        $this->assertSame('id', $this->getFieldProperty($idField));

        // filename
        $filenameField = $fields[1];
        $this->assertSame('filename', $this->getFieldProperty($filenameField));

        // size -> NumberField with format callable
        $sizeField = $fields[2];
        $this->assertInstanceOf(NumberField::class, $sizeField);
        $this->assertSame('size', $this->getFieldProperty($sizeField));
        $formatCallable = $this->getFieldFormatCallable($sizeField);

        $this->assertIsCallable($formatCallable);
        $this->assertSame('-', $formatCallable(null));
        $this->assertSame(Helper::formatBytes(1024), $formatCallable(1024));

        // createdAt
        $createdAtField = $fields[3];
        $this->assertSame('createdAt', $this->getFieldProperty($createdAtField));
    }

    private function getFieldProperty(object $field): string
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $prop->setAccessible(true);
        $dto = $prop->getValue($field);

        return $dto->getProperty();
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

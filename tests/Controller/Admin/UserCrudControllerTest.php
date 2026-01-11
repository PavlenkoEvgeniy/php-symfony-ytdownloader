<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\UserCrudController;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudControllerTest extends TestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(User::class, UserCrudController::getEntityFqcn());
    }

    public function testConfigureFieldsForEditPasswordIsPasswordType(): void
    {
        $hasher     = $this->createMock(UserPasswordHasherInterface::class);
        $controller = new UserCrudController($hasher);

        $fields = \iterator_to_array($controller->configureFields(Crud::PAGE_EDIT));

        $passwordField = null;
        foreach ($fields as $f) {
            if ('password' === $this->getFieldProperty($f)) {
                $passwordField = $f;
                break;
            }
        }

        $this->assertNotNull($passwordField);
        $this->assertSame(PasswordType::class, $this->getFieldFormType($passwordField));
        $this->assertSame(false, $this->getFieldFormTypeOption($passwordField, 'mapped'));
        $this->assertTrue((bool) $this->getFieldFormTypeOption($passwordField, 'required'));
    }

    public function testConfigureFieldsForNewPasswordIsRepeatedType(): void
    {
        $hasher     = $this->createMock(UserPasswordHasherInterface::class);
        $controller = new UserCrudController($hasher);

        $fields = \iterator_to_array($controller->configureFields(Crud::PAGE_NEW));

        $passwordField = null;
        foreach ($fields as $f) {
            if ('password' === $this->getFieldProperty($f)) {
                $passwordField = $f;
                break;
            }
        }

        $this->assertNotNull($passwordField);
        $this->assertSame(RepeatedType::class, $this->getFieldFormType($passwordField));
        $this->assertSame(false, $this->getFieldFormTypeOption($passwordField, 'mapped'));
        $this->assertSame(PasswordType::class, $this->getFieldFormTypeOption($passwordField, 'type'));
        $this->assertTrue((bool) $this->getFieldFormTypeOption($passwordField, 'required'));
    }

    public function testEmailIsRequiredAndIsEnabledRenderedAsSwitchAndRolesConfigured(): void
    {
        $hasher     = $this->createMock(UserPasswordHasherInterface::class);
        $controller = new UserCrudController($hasher);

        $fields = \iterator_to_array($controller->configureFields(Crud::PAGE_INDEX));

        $emailFound     = false;
        $isEnabledFound = false;
        $rolesFound     = false;
        foreach ($fields as $f) {
            $prop = $this->getFieldProperty($f);
            if ('email' === $prop) {
                $emailFound = true;
                $this->assertTrue((bool) $this->getFieldFormTypeOption($f, 'required'));
            }

            if ('isEnabled' === $prop) {
                $isEnabledFound = true;
                $this->assertSame(false, $this->getFieldCustomOption($f, BooleanField::OPTION_RENDER_AS_SWITCH));
            }

            if ('roles' === $prop) {
                $rolesFound = true;
                $this->assertSame(['admin' => 'ROLE_ADMIN', 'user' => 'ROLE_USER'], $this->getFieldCustomOption($f, ChoiceField::OPTION_CHOICES));
                $this->assertTrue((bool) $this->getFieldCustomOption($f, ChoiceField::OPTION_ALLOW_MULTIPLE_CHOICES));
                $this->assertTrue((bool) $this->getFieldCustomOption($f, ChoiceField::OPTION_RENDER_EXPANDED));
                $this->assertTrue((bool) $this->getFieldCustomOption($f, ChoiceField::OPTION_RENDER_AS_BADGES));
            }
        }

        $this->assertTrue($emailFound, 'email field not found');
        $this->assertTrue($isEnabledFound, 'isEnabled field not found');
        $this->assertTrue($rolesFound, 'roles field not found');
    }

    public function testHashPasswordClosureDoesNothingWhenFormInvalidOrPasswordNull(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hashPassword');

        $controller = new UserCrudController($hasher);

        $closure = $this->getHashPasswordClosure($controller);
        $this->assertInstanceOf(\Closure::class, $closure);

        // case: invalid form
        $formInvalid = $this->createMock(FormInterface::class);
        $formInvalid->method('isValid')->willReturn(false);
        $eventInvalid = new FormEvent($formInvalid, null);

        $closure($eventInvalid);

        // case: valid but password null
        $formValid = $this->createMock(FormInterface::class);
        $formValid->method('isValid')->willReturn(true);

        $passwordForm = $this->createMock(FormInterface::class);
        $passwordForm->method('getData')->willReturn(null);

        $formValid->method('get')->with('password')->willReturn($passwordForm);
        $formValid->method('getData')->willReturn(new User());

        $eventValid = new FormEvent($formValid, null);

        $closure($eventValid);
    }

    public function testHashPasswordClosureHashesAndSetsPasswordWhenProvided(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hashPassword')->willReturn('hashed');

        $controller = new UserCrudController($hasher);

        $closure = $this->getHashPasswordClosure($controller);
        $this->assertInstanceOf(\Closure::class, $closure);

        $user = new User();

        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(true);

        $passwordForm = $this->createMock(FormInterface::class);
        $passwordForm->method('getData')->willReturn('plain');

        $form->method('get')->with('password')->willReturn($passwordForm);
        $form->method('getData')->willReturn($user);

        $event = new FormEvent($form, null);

        $closure($event);

        $this->assertSame('hashed', $user->getPassword());
    }

    private function getFieldProperty(object $field): string
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $prop->setAccessible(true);
        $dto = $prop->getValue($field);

        return $dto->getProperty();
    }

    private function getFieldFormType(object $field): ?string
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $prop->setAccessible(true);
        $dto = $prop->getValue($field);

        return $dto->getFormType();
    }

    private function getFieldFormTypeOption(object $field, string $option): mixed
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $prop->setAccessible(true);
        $dto = $prop->getValue($field);

        return $dto->getFormTypeOption($option);
    }

    private function getFieldCustomOption(object $field, string $option): mixed
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $prop->setAccessible(true);
        $dto = $prop->getValue($field);

        return $dto->getCustomOption($option);
    }

    private function getHashPasswordClosure(UserCrudController $controller): \Closure
    {
        $ref    = new \ReflectionObject($controller);
        $method = $ref->getMethod('hashPassword');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }
}

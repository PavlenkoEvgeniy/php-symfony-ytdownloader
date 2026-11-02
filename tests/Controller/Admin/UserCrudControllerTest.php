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
    /**
     * Verifies the CRUD controller is bound to the User entity.
     */
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(User::class, UserCrudController::getEntityFqcn());
    }

    /**
     * Verifies the edit form uses an unmapped required password field.
     */
    public function testConfigureFieldsForEditPasswordIsPasswordType(): void
    {
        $hasher     = $this->createStub(UserPasswordHasherInterface::class);
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

    /**
     * Verifies the new form uses an unmapped repeated password field.
     */
    public function testConfigureFieldsForNewPasswordIsRepeatedType(): void
    {
        $hasher     = $this->createStub(UserPasswordHasherInterface::class);
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

    /**
     * Verifies email, enabled switch and role choice field options are configured.
     */
    public function testEmailIsRequiredAndIsEnabledRenderedAsSwitchAndRolesConfigured(): void
    {
        $hasher     = $this->createStub(UserPasswordHasherInterface::class);
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
                $this->assertSame(true, $this->getFieldCustomOption($f, BooleanField::OPTION_RENDER_AS_SWITCH));
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

    /**
     * Verifies the hash closure leaves the entity untouched for invalid forms or empty passwords.
     */
    public function testHashPasswordClosureDoesNothingWhenFormInvalidOrPasswordNull(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hashPassword');

        $controller = new UserCrudController($hasher);

        $closure = $this->getHashPasswordClosure($controller);

        // case: invalid form
        $formInvalid = $this->createStub(FormInterface::class);
        $formInvalid->method('isValid')->willReturn(false);
        $eventInvalid = new FormEvent($formInvalid, null);

        $closure($eventInvalid);

        // case: valid but password null
        $formValid = $this->createStub(FormInterface::class);
        $formValid->method('isValid')->willReturn(true);

        $passwordForm = $this->createStub(FormInterface::class);
        $passwordForm->method('getData')->willReturn(null);

        $formValid->method('get')->willReturn($passwordForm);
        $formValid->method('getData')->willReturn(new User());

        $eventValid = new FormEvent($formValid, null);

        $closure($eventValid);
    }

    /**
     * Verifies the hash closure ignores a valid form whose data is not a user.
     */
    public function testHashPasswordClosureIgnoresNonUserData(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->never())->method('hashPassword');

        $controller = new UserCrudController($hasher);

        $closure = $this->getHashPasswordClosure($controller);

        $form = $this->createStub(FormInterface::class);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(new \stdClass());

        $passwordForm = $this->createStub(FormInterface::class);
        $passwordForm->method('getData')->willReturn('plain');

        $form->method('get')->willReturn($passwordForm);

        $closure(new FormEvent($form, null));
    }

    /**
     * Verifies the hash closure stores the hashed value on the form entity.
     */
    public function testHashPasswordClosureHashesAndSetsPasswordWhenProvided(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())->method('hashPassword')->willReturn('hashed');

        $controller = new UserCrudController($hasher);

        $closure = $this->getHashPasswordClosure($controller);

        $user = new User();

        $form = $this->createStub(FormInterface::class);
        $form->method('isValid')->willReturn(true);

        $passwordForm = $this->createStub(FormInterface::class);
        $passwordForm->method('getData')->willReturn('plain');

        $form->method('get')->willReturn($passwordForm);
        $form->method('getData')->willReturn($user);

        $event = new FormEvent($form, null);

        $closure($event);

        $this->assertSame('hashed', $user->getPassword());
    }

    private function getFieldProperty(object $field): string
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $dto  = $prop->getValue($field);

        return $dto->getProperty();
    }

    private function getFieldFormType(object $field): ?string
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $dto  = $prop->getValue($field);

        return $dto->getFormType();
    }

    private function getFieldFormTypeOption(object $field, string $option): mixed
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $dto  = $prop->getValue($field);

        return $dto->getFormTypeOption($option);
    }

    private function getFieldCustomOption(object $field, string $option): mixed
    {
        $ref  = new \ReflectionObject($field);
        $prop = $ref->getProperty('dto');
        $dto  = $prop->getValue($field);

        return $dto->getCustomOption($option);
    }

    private function getHashPasswordClosure(UserCrudController $controller): \Closure
    {
        $ref    = new \ReflectionObject($controller);
        $method = $ref->getMethod('hashPassword');

        return $method->invoke($controller);
    }
}

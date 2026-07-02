<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\ChangePasswordForm;
use Symfony\Component\Form\Test\TypeTestCase;

final class ChangePasswordFormTest extends TypeTestCase
{
    public function testFormHasCurrentPasswordField(): void
    {
        $form = $this->factory->create(ChangePasswordForm::class);

        $this->assertTrue($form->has('currentPassword'));
    }

    public function testFormHasNewPasswordField(): void
    {
        $form = $this->factory->create(ChangePasswordForm::class);

        $this->assertTrue($form->has('newPassword'));
    }

    public function testCurrentPasswordFieldIsUnmapped(): void
    {
        $form                 = $this->factory->create(ChangePasswordForm::class);
        $currentPasswordField = $form->get('currentPassword');

        $this->assertFalse($currentPasswordField->getConfig()->getMapped());
    }

    public function testNewPasswordFieldIsUnmapped(): void
    {
        $form             = $this->factory->create(ChangePasswordForm::class);
        $newPasswordField = $form->get('newPassword');

        $this->assertFalse($newPasswordField->getConfig()->getMapped());
    }

    public function testNewPasswordFieldIsRepeatedType(): void
    {
        $form             = $this->factory->create(ChangePasswordForm::class);
        $newPasswordField = $form->get('newPassword');

        $this->assertTrue($newPasswordField->has('first'));
        $this->assertTrue($newPasswordField->has('second'));
    }

    public function testFormCanBeSubmittedWithValidData(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashed-old-password');

        $form = $this->factory->create(ChangePasswordForm::class, $user);

        $form->submit([
            'currentPassword' => 'old-password',
            'newPassword'     => [
                'first'  => 'new-password',
                'second' => 'new-password',
            ],
        ]);

        $this->assertTrue($form->isSynchronized());
    }

    public function testFormInvalidWhenNewPasswordsDoNotMatch(): void
    {
        $form = $this->factory->create(ChangePasswordForm::class);

        $form->submit([
            'currentPassword' => 'old-password',
            'newPassword'     => [
                'first'  => 'new-password-1',
                'second' => 'new-password-2',
            ],
        ]);

        $this->assertFalse($form->isValid());
    }

    public function testFormHasCorrectLabelForCurrentPassword(): void
    {
        $form                 = $this->factory->create(ChangePasswordForm::class);
        $currentPasswordField = $form->get('currentPassword');

        $this->assertSame('Current Password:', $currentPasswordField->getConfig()->getOption('label'));
    }

    public function testFormHasCorrectLabelForNewPassword(): void
    {
        $form             = $this->factory->create(ChangePasswordForm::class);
        $newPasswordField = $form->get('newPassword');

        $this->assertSame('New Password:', $newPasswordField->getConfig()->getOption('first_options')['label']);
        $this->assertSame('Repeat New Password:', $newPasswordField->getConfig()->getOption('second_options')['label']);
    }
}

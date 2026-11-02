<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\ChangePasswordForm;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

final class ChangePasswordFormTest extends TypeTestCase
{
    /**
     * Verifies the form exposes a current password field.
     */
    public function testFormHasCurrentPasswordField(): void
    {
        $form = $this->factory->create(ChangePasswordForm::class);

        $this->assertTrue($form->has('currentPassword'));
    }

    /**
     * Verifies the form exposes a new password field.
     */
    public function testFormHasNewPasswordField(): void
    {
        $form = $this->factory->create(ChangePasswordForm::class);

        $this->assertTrue($form->has('newPassword'));
    }

    /**
     * Verifies the current password field is not mapped to the entity.
     */
    public function testCurrentPasswordFieldIsUnmapped(): void
    {
        $form                 = $this->factory->create(ChangePasswordForm::class);
        $currentPasswordField = $form->get('currentPassword');

        $this->assertFalse($currentPasswordField->getConfig()->getMapped());
    }

    /**
     * Verifies the new password field is not mapped to the entity.
     */
    public function testNewPasswordFieldIsUnmapped(): void
    {
        $form             = $this->factory->create(ChangePasswordForm::class);
        $newPasswordField = $form->get('newPassword');

        $this->assertFalse($newPasswordField->getConfig()->getMapped());
    }

    /**
     * Verifies the new password field repeats its input twice.
     */
    public function testNewPasswordFieldIsRepeatedType(): void
    {
        $form             = $this->factory->create(ChangePasswordForm::class);
        $newPasswordField = $form->get('newPassword');

        $this->assertTrue($newPasswordField->has('first'));
        $this->assertTrue($newPasswordField->has('second'));
    }

    /**
     * Verifies a valid submission keeps the form synchronized.
     */
    public function testFormCanBeSubmittedWithValidData(): void
    {
        $user = new User();
        $user->setEmail('test@test.local');
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

    /**
     * Verifies mismatched new passwords make the form invalid.
     */
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

    /**
     * Verifies the current password field has the expected label.
     */
    public function testFormHasCorrectLabelForCurrentPassword(): void
    {
        $form                 = $this->factory->create(ChangePasswordForm::class);
        $currentPasswordField = $form->get('currentPassword');

        $this->assertSame('Current Password:', $currentPasswordField->getConfig()->getOption('label'));
    }

    /**
     * Verifies both repeated password fields have the expected labels.
     */
    public function testFormHasCorrectLabelForNewPassword(): void
    {
        $form             = $this->factory->create(ChangePasswordForm::class);
        $newPasswordField = $form->get('newPassword');

        $this->assertSame('New Password:', $newPasswordField->getConfig()->getOption('first_options')['label']);
        $this->assertSame('Repeat New Password:', $newPasswordField->getConfig()->getOption('second_options')['label']);
    }

    protected function getTypes(): array
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', \dirname(__DIR__, 2) . '/translations/messages.en.yaml', 'en');

        return [new ChangePasswordForm($translator)];
    }
}

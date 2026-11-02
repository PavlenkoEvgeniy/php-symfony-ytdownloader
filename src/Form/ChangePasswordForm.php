<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ChangePasswordForm extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, [
            'label'  => $this->translator->trans('form.current_password'),
            'mapped' => false,
        ])
            ->add('newPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'invalid_message' => $this->translator->trans('form.passwords_must_match'),
                'options'         => [
                    'attr' => [
                        'class' => 'password-field',
                    ],
                ],
                'required'      => true,
                'first_options' => [
                    'label' => $this->translator->trans('form.new_password'),
                ],
                'second_options' => [
                    'label' => $this->translator->trans('form.repeat_new_password'),
                ],
                'mapped' => false,
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

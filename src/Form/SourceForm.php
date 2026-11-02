<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Source;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SourceForm extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('filename', TextType::class, [
            'label'       => $this->translator->trans('form.filename'),
            'required'    => true,
            'trim'        => true,
            'constraints' => [
                new Length(min: 5, max: 255),
                new NotBlank([
                    'normalizer' => 'trim',
                ]),
                new NotNull(),
                new Regex(
                    pattern: '/^[^\/\\\\]+$/',
                    message: 'form.filename.invalid',
                ),
                new NotEqualTo(value: '.', message: 'form.filename.invalid'),
                new NotEqualTo(value: '..', message: 'form.filename.invalid'),
            ],
        ])
            ->add('filepath', TextType::class, [
                'label' => $this->translator->trans('form.filepath'),
                'attr'  => [
                    'readonly' => true,
                ],
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => $this->translator->trans('form.description'),
                'attr'  => [
                    'rows' => 5,
                ],
                'required' => false,
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Source::class,
        ]);
    }
}

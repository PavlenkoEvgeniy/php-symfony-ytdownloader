<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

final class DownloadType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('link', TextType::class, [
                'required'    => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 10, max: 255),
                    new Url(requireTld: true),
                ],
                'label' => 'Link:',
                'attr'  => [
                    'placeholder' => 'https://youtube.com/some-example-video',
                ],
            ])
            ->add('quality', ChoiceType::class, [
                'choices' => [
                    'BEST QUALITY'     => 'best',
                    'MODERATE QUALITY' => 'moderate',
                    'POOR QUALITY'     => 'poor',
                    'AUDIO ONLY'       => 'audio',
                ],
                'data'        => 'moderate',
                'label'       => 'Quality:',
                'mapped'      => false,
                'required'    => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Start download! 🚀',
                'attr'  => [
                    'class' => 'btn btn-outline-primary mt-2',
                ],
            ])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}

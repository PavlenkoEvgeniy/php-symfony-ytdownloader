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

class DownloadType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('link', TextType::class, [
                'required'    => true,
                'constraints' => [
                    new NotBlank(),
                    new Length(['min'=> 10]),
                    new Url([
                        'requireTld' => true,
                    ]),
                ],
            ])
            ->add('quality', ChoiceType::class, [
                'choices' => [
                    'BEST - 1080p (best video + best audio)'         => 'best',
                    'MODERATE - 720p (moderate video + best audio)'  => 'moderate',
                    'POOR - 380p (poor video + best audio)'          => 'poor',
                    'DRAFT - 240p (draft video + best audio)'        => 'draft',
                    'AUDIO - opus (only audio will be downloaded)'   => 'audio',
                ],
                'mapped'      => false,
                'required'    => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('submit', SubmitType::class)
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

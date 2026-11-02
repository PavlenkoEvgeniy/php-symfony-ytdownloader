<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DownloadForm extends AbstractType
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $session = $this->requestStack->getSession();

        $builder->add('link', TextType::class, [
            'required'    => true,
            'constraints' => [
                new NotBlank(),
                new Length(min: 10, max: 255),
                new Url(requireTld: true),
            ],
            'label' => $this->translator->trans('form.link'),
            'attr'  => [
                'placeholder' => $this->translator->trans('form.link_placeholder'),
            ],
        ])
            ->add('quality', ChoiceType::class, [
                'choices' => [
                    $this->translator->trans('form.quality.best')     => 'best',
                    $this->translator->trans('form.quality.moderate') => 'moderate',
                    $this->translator->trans('form.quality.poor')     => 'poor',
                    $this->translator->trans('form.quality.audio')    => 'audio',
                ],
                'data'        => $session->has('lastSelectedQuality') ? $session->get('lastSelectedQuality') : 'moderate',
                'label'       => $this->translator->trans('form.quality'),
                'mapped'      => false,
                'required'    => true,
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => $this->translator->trans('form.submit'),
                'attr'  => [
                    'class' => 'btn btn-outline-primary mt-2',
                ],
            ])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

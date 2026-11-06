<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DownloadSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DownloadSettingsForm extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('webIntakeEnabled', CheckboxType::class, [
                'label'    => $this->translator->trans('admin.settings.web_intake'),
                'required' => false,
            ])
            ->add('progressIntervalMinutes', IntegerType::class, [
                'label'       => $this->translator->trans('admin.settings.progress_interval'),
                'help'        => $this->translator->trans('admin.settings.progress_interval_hint'),
                'required'    => true,
                'constraints' => [
                    new Range(min: 0, max: 1440),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => $this->translator->trans('admin.settings.save'),
                'attr'  => [
                    'class' => 'btn btn-primary',
                ],
            ])
        ;
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => DownloadSettings::class,
            'csrf_token_id' => 'download_settings',
        ]);
    }
}

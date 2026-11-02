<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Source;
use App\Helper\Helper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @extends AbstractCrudController<Source> */
final class SourceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    #[\Override]
    public static function getEntityFqcn(): string
    {
        return Source::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', $this->trans('admin.field.id', 'ID'));
        yield TextField::new('filename', $this->trans('admin.field.filename', 'Filename'));
        yield NumberField::new('size', $this->trans('admin.field.size', 'Size'))
            ->formatValue(function ($value) {
                if (null === $value || 0 === $value) {
                    return '-';
                }

                return Helper::formatBytes($value);
            })
        ;
        yield DateTimeField::new('createdAt', $this->trans('admin.field.created_at', 'Created at'));
    }

    private function trans(string $key, string $fallback): string
    {
        return $this->translator?->trans($key) ?? $fallback;
    }
}

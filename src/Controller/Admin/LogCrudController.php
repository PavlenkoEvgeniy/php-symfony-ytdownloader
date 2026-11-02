<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Log;
use App\Helper\Helper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @extends AbstractCrudController<Log> */
final class LogCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    #[\Override]
    public static function getEntityFqcn(): string
    {
        return Log::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW, Action::DELETE);
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort(['id' => 'DESC'])
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', $this->trans('admin.field.id', 'ID'))->hideOnForm();
        yield TextField::new('type', $this->trans('admin.field.result', 'Result'));
        yield TextField::new('message', $this->trans('admin.field.message', 'Message'));
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

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Log;
use App\Helper\Helper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @psalm-suppress MissingTemplateParam
 */
class LogCrudController extends AbstractCrudController
{
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
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm();
        yield TextField::new('type', 'Result');
        yield TextField::new('message', 'Message');
        yield NumberField::new('size', 'Size')
            ->formatValue(function ($value) {
                if (!$value) {
                    return '-';
                }

                return Helper::formatBytes($value);
            });
        yield DateTimeField::new('createdAt', 'Created at');
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AuditEvent;
use App\Enum\AuditAction;
use App\Enum\AuditChannel;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @extends AbstractCrudController<AuditEvent> */
final class AuditEventCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    #[\Override]
    public static function getEntityFqcn(): string
    {
        return AuditEvent::class;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    #[\Override]
    public function configureAssets(Assets $assets): Assets
    {
        return parent::configureAssets($assets)
            ->addCssFile('styles/admin-audit-event.css')
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort(['occurredAt' => 'DESC'])
            ->setPaginatorPageSize(50)
        ;
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('action')->setChoices($this->actionChoices()))
            ->add(ChoiceFilter::new('channel')->setChoices($this->channelChoices()))
            ->add(BooleanFilter::new('success'))
            ->add(TextFilter::new('actorLabel'))
            ->add(TextFilter::new('targetLabel'))
            ->add(TextFilter::new('ip'))
            ->add(DateTimeFilter::new('occurredAt'))
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', $this->trans('admin.field.id', 'ID'))->onlyOnIndex();
        yield DateTimeField::new('occurredAt', $this->trans('admin.field.occurred_at', 'When'))
            ->setFormat('yyyy-MM-dd HH:mm:ss');
        yield TextField::new('action', $this->trans('admin.field.action', 'Action'))
            ->formatValue(fn (string $value): string => $this->trans(\sprintf('admin.audit.action.%s', $value)));
        yield TextField::new('channel', $this->trans('admin.field.channel', 'Channel'))
            ->formatValue(fn (string $value): string => $this->trans(\sprintf('admin.audit.channel.%s', $value)));
        yield TextField::new('actorLabel', $this->trans('admin.field.actor', 'Actor'))
            ->formatValue(static fn (?string $value): string => $value ?? '-');
        yield TextField::new('targetLabel', $this->trans('admin.field.target', 'Target'))
            ->formatValue(static fn (?string $value): string => $value ?? '-');
        yield TextField::new('ip', $this->trans('admin.field.ip', 'IP'));
        yield BooleanField::new('success', $this->trans('admin.field.success', 'Success'))
            ->renderAsSwitch(false);
        yield TextField::new('actorType', $this->trans('admin.field.actor_type', 'Actor type'))->onlyOnDetail();
        yield TextField::new('targetType', $this->trans('admin.field.target_type', 'Target type'))->onlyOnDetail();
        yield TextField::new('userAgent', $this->trans('admin.field.user_agent', 'User agent'))->onlyOnDetail();
        yield TextField::new('requestId', $this->trans('admin.field.request_id', 'Request ID'))->onlyOnDetail();
        yield TextField::new('metadataJson', $this->trans('admin.field.metadata', 'Details'))
            ->onlyOnDetail()
            ->formatValue(static fn (?string $value): string => $value ?? '-')
        ;
    }

    /**
     * @return array<string, string>
     */
    private function actionChoices(): array
    {
        $choices = [];

        foreach (AuditAction::cases() as $action) {
            $choices[$this->trans(\sprintf('admin.audit.action.%s', $action->value))] = $action->value;
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function channelChoices(): array
    {
        $choices = [];

        foreach (AuditChannel::cases() as $channel) {
            $choices[$this->trans(\sprintf('admin.audit.channel.%s', $channel->value))] = $channel->value;
        }

        return $choices;
    }

    private function trans(string $key, string $fallback = ''): string
    {
        return $this->translator?->trans($key) ?? ('' !== $fallback ? $fallback : $key);
    }
}

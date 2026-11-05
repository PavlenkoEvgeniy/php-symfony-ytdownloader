<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @extends AbstractCrudController<User> */
final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    #[\Override]
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultRowAction(Action::DETAIL)
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', $this->trans('admin.field.id', 'ID'))
            ->onlyOnIndex();

        yield ImageField::new('avatar', $this->trans('admin.field.avatar', 'Avatar'))
            ->setBasePath('uploads/avatars')
            ->setUploadDir('public/uploads/avatars')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]');

        yield EmailField::new('email', $this->trans('admin.field.email', 'Email'))
            ->setRequired(true);

        yield TextField::new('telegramUsername', $this->trans('admin.field.telegram', 'Telegram'))
            ->onlyOnDetail()
            ->formatValue(function (mixed $value, ?User $entity): string {
                if (!$entity instanceof User || !$entity->isTelegramLinked()) {
                    return $this->trans('admin.field.telegram_not_linked', 'Not linked');
                }

                $account = \is_string($value) && '' !== $value
                    ? \sprintf('@%s', $value)
                    : (string) $entity->getTelegramUserId();

                return $this->trans(
                    'admin.field.telegram_linked',
                    \sprintf('Linked as %s', $account),
                    ['%account%' => $account],
                );
            });

        yield BooleanField::new('isEnabled', $this->trans('admin.field.enabled', 'Enabled'))
            ->renderAsSwitch();

        yield ChoiceField::new('roles', $this->trans('admin.field.roles', 'Roles'))
            ->setChoices([
                $this->trans('admin.field.role_admin', 'admin') => 'ROLE_ADMIN',
                $this->trans('admin.field.role_user', 'user')   => 'ROLE_USER',
            ])
            ->setRequired(true)
            ->allowMultipleChoices()
            ->renderExpanded()
            ->renderAsBadges();

        yield DateTimeField::new('createdAt', $this->trans('admin.field.created_at_capitalized', 'Created At'))
            ->hideOnForm();

        if (Crud::PAGE_EDIT === $pageName) {
            yield TextField::new('password')
                ->setFormType(PasswordType::class)
                ->setFormTypeOptions([
                    'mapped' => false,
                ])
                ->setRequired(true)
                ->onlyOnForms();
        } else {
            yield TextField::new('password')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type'           => PasswordType::class,
                    'first_options'  => ['label' => $this->trans('admin.field.password', 'Password')],
                    'second_options' => ['label' => $this->trans('admin.field.repeat', '(Repeat)')],
                    'mapped'         => false,
                ])
                ->setRequired(Crud::PAGE_NEW === $pageName)
                ->onlyOnForms();
        }
    }

    #[\Override]
    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createNewFormBuilder($entityDto, $formOptions, $context);

        return $this->addPasswordEventListener($formBuilder);
    }

    #[\Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        return $this->addPasswordEventListener($formBuilder);
    }

    private function addPasswordEventListener(FormBuilderInterface $formBuilder): FormBuilderInterface
    {
        return $formBuilder->addEventListener(FormEvents::POST_SUBMIT, $this->hashPassword());
    }

    private function hashPassword(): \Closure
    {
        return function (FormEvent $event) {
            $form = $event->getForm();

            if (!$form->isValid()) {
                return;
            }

            $password = $form->get('password')->getData();

            if (!\is_string($password) || '' === $password) {
                return;
            }

            $user = $form->getData();

            if (!$user instanceof User) {
                return;
            }

            $hashedPassword = $this->userPasswordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
        };
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $key, string $fallback, array $parameters = []): string
    {
        return $this->translator?->trans($key, $parameters) ?? $fallback;
    }
}

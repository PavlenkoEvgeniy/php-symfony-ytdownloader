<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
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
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        public UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    #[\Override]
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        $isNewOrEditPage = \in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true);

        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        yield ImageField::new('avatar', 'Avatar')
            ->setBasePath('uploads/avatars')
            ->setUploadDir('public/uploads/avatars')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]');

        yield EmailField::new('email', 'Email')
            ->setRequired(true);

        yield BooleanField::new('isEnabled', 'Enabled')
            ->renderAsSwitch(false);

        yield ChoiceField::new('roles', 'Roles')
            ->setChoices([
                'admin' => 'ROLE_ADMIN',
                'user'  => 'ROLE_USER',
            ])
            ->setRequired(true)
            ->allowMultipleChoices()
            ->renderExpanded()
            ->renderAsBadges();

        yield DateTimeField::new('createdAt', 'Created At')
            ->hideOnForm();

        if (Crud::PAGE_EDIT === $pageName) {
            yield TextField::new('password')
                ->setFormType(PasswordType::class)
                ->setFormTypeOptions([
                    'mapped' => false,
                ])
                ->setRequired($isNewOrEditPage)
                ->onlyOnForms();
        } else {
            yield TextField::new('password')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type'           => PasswordType::class,
                    'first_options'  => ['label' => 'Password'],
                    'second_options' => ['label' => '(Repeat)'],
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
        return function ($event) {
            $form = $event->getForm();
            if (!$form->isValid()) {
                return;
            }
            $password = $form->get('password')->getData();
            if (null === $password) {
                return;
            }

            /** @var User $user */
            $user = $form->getData();

            $hash = $this->userPasswordHasher->hashPassword($user, $password);
            $user->setPassword($hash);
        };
    }
}

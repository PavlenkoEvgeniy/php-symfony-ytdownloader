<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * @return array<string>
     */
    #[\Override]
    public static function getGroups(): array
    {
        return ['user', 'all'];
    }

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $items = [
            [
                'email'     => 'admin@admin.local',
                'password'  => 'admin123456',
                'roles'     => ['ROLE_ADMIN'],
                'isEnabled' => true,
            ],
            [
                'email'     => 'user@test.local',
                'password'  => 'user123456',
                'roles'     => ['ROLE_USER'],
                'isEnabled' => true,
            ],
            [
                'email'     => 'admin.disabled@admin.local',
                'password'  => 'admin.disabled123456',
                'roles'     => ['ROLE_ADMIN'],
                'isEnabled' => false,
            ],
            [
                'email'     => 'user.disabled@test.local',
                'password'  => 'user.disabled123456',
                'roles'     => ['ROLE_USER'],
                'isEnabled' => false,
            ],
        ];

        foreach ($items as $item) {
            $user = new User();
            $user
                ->setEmail($item['email'])
                ->setPassword($this->passwordHasher->hashPassword(
                    $user,
                    $item['password'])
                )
                ->setRoles($item['roles'])
                ->setIsEnabled($item['isEnabled'])
            ;

            $manager->persist($user);
        }

        $manager->flush();
    }
}

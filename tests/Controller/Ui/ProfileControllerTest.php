<?php

declare(strict_types=1);

namespace App\Tests\Controller\Ui;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProfileControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;
    private UserPasswordHasherInterface $passwordHasher;

    public function setUp(): void
    {
        $this->client = static::createClient();

        $this->userRepository = $this->getContainer()->get(UserRepository::class);
        $this->passwordHasher = $this->getContainer()->get(UserPasswordHasherInterface::class);
    }

    public function testIndexPageIsOpeningOk(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/ui/profile');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Profile');
        $this->assertSelectorTextContains('h4', 'Change password:');
    }

    public function testThemePageRequiresAuthentication(): void
    {
        $this->client->request('GET', '/ui/profile/theme');
        $this->assertResponseRedirects();
    }

    public function testThemePageIsOpeningOk(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ui/profile/theme');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Theme:');

        $this->assertSelectorExists('button#theme-save');
        $this->assertCount(1, $crawler->filter('[name="theme"][value="auto"]'));
        $this->assertCount(1, $crawler->filter('[name="theme"][value="dark"]'));
        $this->assertCount(1, $crawler->filter('[name="theme"][value="light"]'));
    }

    public function testIndexPageDoesNotContainThemeForm(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $this->client->request('GET', '/ui/profile');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('input[name="theme"]');
        $this->assertSelectorExists('a[href="/ui/profile/theme"]');
    }

    public function testBaseLayoutContainsThemeScript(): void
    {
        $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString("'ytdownloader-theme'", $content);
        $this->assertStringContainsString('dataset.bsTheme', $content);
        $this->assertStringContainsString('prefers-color-scheme', $content);
    }

    public function testChangePasswordIsOk(): void
    {
        $user = $this->userRepository->findOneByEmail('admin@admin.local');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ui/profile');
        $this->assertResponseIsSuccessful();

        $currentPassword = 'admin123456';
        $newPassword     = 'Password123!';

        $form = $crawler->selectButton('Change')->form();

        $form['change_password_form[currentPassword]']     = $currentPassword;
        $form['change_password_form[newPassword][first]']  = $newPassword;
        $form['change_password_form[newPassword][second]'] = $newPassword;

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-success', 'Your password was successfully updated.');

        // Refresh user
        $user                 = $this->userRepository->findOneByEmail('admin@admin.local');
        $checkPasswordIsValid = $this->passwordHasher->isPasswordValid($user, $newPassword);

        $this->assertTrue($checkPasswordIsValid);
    }
}

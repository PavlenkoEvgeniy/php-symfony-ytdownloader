<?php

declare(strict_types=1);

use DG\BypassFinals;
use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__) . '/vendor/autoload.php';

// Allow mocking of final classes in tests (intentionally risky for unit seams).
BypassFinals::enable();

if (\method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(\dirname(__DIR__) . '/.env');
}

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) === 'test') {
    $projectDir    = \dirname(__DIR__);
    $secretKeyPath = $_SERVER['JWT_SECRET_KEY'] ?? $_ENV['JWT_SECRET_KEY'] ?? \getenv('JWT_SECRET_KEY') ?: '';
    $publicKeyPath = $_SERVER['JWT_PUBLIC_KEY'] ?? $_ENV['JWT_PUBLIC_KEY'] ?? \getenv('JWT_PUBLIC_KEY') ?: '';
    $passphrase    = $_SERVER['JWT_PASSPHRASE'] ?? $_ENV['JWT_PASSPHRASE'] ?? \getenv('JWT_PASSPHRASE') ?: 'change_me_please';

    if ('' !== $secretKeyPath) {
        $secretKeyPath = \str_replace('%kernel.project_dir%', $projectDir, $secretKeyPath);
    }
    if ('' !== $publicKeyPath) {
        $publicKeyPath = \str_replace('%kernel.project_dir%', $projectDir, $publicKeyPath);
    }

    if ('' !== $secretKeyPath && '' !== $publicKeyPath && (!\file_exists($secretKeyPath) || !\file_exists($publicKeyPath))) {
        if (!\function_exists('openssl_pkey_new')) {
            throw new RuntimeException('OpenSSL extension is required to generate JWT keys for tests.');
        }

        $keyDir = \dirname($secretKeyPath);
        if (!\is_dir($keyDir)) {
            \mkdir($keyDir, 0700, true);
        }

        $privateKey = \openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 4096,
        ]);
        if (false === $privateKey) {
            throw new RuntimeException('Unable to generate JWT private key.');
        }

        if (!\openssl_pkey_export($privateKey, $privateKeyPem, $passphrase)) {
            throw new RuntimeException('Unable to export JWT private key.');
        }

        $details = \openssl_pkey_get_details($privateKey);
        if (false === $details || empty($details['key'])) {
            throw new RuntimeException('Unable to extract JWT public key.');
        }

        \file_put_contents($secretKeyPath, $privateKeyPem);
        \chmod($secretKeyPath, 0600);
        \file_put_contents($publicKeyPath, $details['key']);
    }
}

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

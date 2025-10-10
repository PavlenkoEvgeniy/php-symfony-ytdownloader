<?php

declare(strict_types=1);

use PhpCsFixer\Finder;
use PhpCsFixer\Config;

return (new Config())
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align',
                '='  => 'align',
            ],
        ],
        'phpdoc_to_comment' => false,
        'native_function_invocation' => [
            'include' => ['@internal'],
            'scope' => 'all',
        ],
    ])
    ->setFinder(
        (new Finder())
            ->in(['src', 'tests', 'migrations'])
            ->exclude(['vendor'])
    )
    ->setLineEnding("\n")
;

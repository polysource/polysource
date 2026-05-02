<?php

declare(strict_types=1);

/*
 * Polysource PHP-CS-Fixer configuration.
 *
 * Rules: PSR-12 + Symfony + PHP 8.2 migration set.
 * Targets all source files under packages/ and examples/, excluding
 * vendor/, var/, and Symfony test app artefacts.
 *
 * Run:
 *   vendor/bin/php-cs-fixer fix              # apply
 *   vendor/bin/php-cs-fixer fix --dry-run    # check only (used in CI)
 */

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/packages',
        __DIR__ . '/examples',
    ])
    ->exclude([
        'vendor',
        'tests/Functional/App/var',
        'tests/Functional/App/cache',
        'var',
    ])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@PHP82Migration' => true,
        '@PHP82Migration:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // Strict declarations
        'declare_strict_types' => true,

        // Imports
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],

        // Native function calls (faster on 8.x)
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],

        // Phpdoc
        'phpdoc_align' => false,
        'phpdoc_separation' => false,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,

        // Misc
        'concat_space' => ['spacing' => 'one'],
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
;

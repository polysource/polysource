<?php

declare(strict_types=1);

/*
 * Polysource PHP-CS-Fixer configuration.
 *
 * Rules: PSR-12 + Symfony + PHP 8.2 migration set.
 * Targets all source files under packages/ (and examples/ once it exists).
 */

$paths = [__DIR__ . '/packages'];
if (\is_dir(__DIR__ . '/examples')) {
    $paths[] = __DIR__ . '/examples';
}

$finder = (new PhpCsFixer\Finder())
    ->in($paths)
    ->exclude([
        'vendor',
        'tests/Functional/App/var',
        'tests/Functional/App/cache',
        'var',
    ])
    // Symfony Flex auto-generates `examples/showcase-demo/config/reference.php`
    // on every `composer install`. The generated content omits
    // `declare(strict_types=1)`, so cs-fixer wants to add it back and gets
    // reverted on the next install — endless ping-pong. Exclude the
    // generated file; it's documented as "for apps only" in its own header.
    ->notPath('showcase-demo/config/reference.php')
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

        'declare_strict_types' => true,

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

        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],

        'phpdoc_align' => false,
        'phpdoc_separation' => false,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,

        'concat_space' => ['spacing' => 'one'],
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
;

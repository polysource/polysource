<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Resources;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Contract tests for the bundle's published assets (`Resources/public/`).
 *
 * The bridge ships its CSS and the filter-button shim as external
 * files (published by `assets:install`) instead of inline `<style>` /
 * `<script>` blocks, so host CSP policies don't need
 * `'unsafe-inline'` for the bridge. These tests lock that contract:
 *
 * 1. The asset files exist where `assets:install` picks them up and
 *    carry their key content (the `--polysource-*` theming variables,
 *    the tab-pane pairing rules, the shim's yield-to-EA guard).
 * 2. Every template reference uses the exact published filename —
 *    a rename on either side without the other breaks silently at
 *    runtime (404'd asset, unstyled chips), never at compile time.
 * 3. The templates stay free of static inline CSS/JS. The single
 *    allowed `<style>` is the hidden-columns block in
 *    `crud/index.html.twig`, which is per-user request-dependent and
 *    cannot be a static file.
 */
final class PublicAssetsContractTest extends TestCase
{
    private const CSS_FILENAME = 'polysource-filter-bridge.css';
    private const SHIM_FILENAME = 'polysource-filter-shim.js';

    private static function bridgeRoot(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function testStylesheetIsPublishedWithThemingVariables(): void
    {
        $css = self::bridgeRoot() . '/Resources/public/' . self::CSS_FILENAME;

        self::assertFileExists($css);
        $content = (string) file_get_contents($css);

        // Theming contract: the documented override surface.
        foreach ([
            '--polysource-accent',
            '--polysource-danger',
            '--polysource-border-color',
            '--polysource-muted-color',
            '--polysource-emphasis-color',
            '--polysource-body-color',
            '--polysource-surface-bg',
            '--polysource-surface-muted-bg',
            '--polysource-subpanel-width',
        ] as $variable) {
            self::assertStringContainsString($variable . ':', $content, \sprintf(
                'The theming variable "%s" is documented public API and must stay declared in %s.',
                $variable,
                self::CSS_FILENAME,
            ));
        }

        // Tab-pane pairing rules moved here from filters.html.twig —
        // pregenerated for tabs 1..12, guarded by @supports so
        // browsers without :has() keep every pane visible.
        self::assertStringContainsString('@supports selector(', $content);
        self::assertStringContainsString(':nth-of-type(12)', $content);
        self::assertStringContainsString(':nth-of-type(n+13)', $content);

        // Subpanel mode CSS moved here from index_subpanel.html.twig.
        self::assertStringContainsString('body.polysource-filter-subpanel #modal-filters', $content);
    }

    public function testFilterShimIsPublished(): void
    {
        $shim = self::bridgeRoot() . '/Resources/public/' . self::SHIM_FILENAME;

        self::assertFileExists($shim);
        $content = (string) file_get_contents($shim);

        // The yield-to-EA guard is the load-bearing part of the shim:
        // without it the shim races EA's own handler and filters
        // silently fail to apply (cf. file header).
        self::assertStringContainsString('window.EasyAdminApp', $content);
        self::assertStringContainsString('polysourceBound', $content);
    }

    public function testIndexTemplateReferencesThePublishedFilenames(): void
    {
        $template = (string) file_get_contents(
            self::bridgeRoot() . '/Resources/views/crud/index.html.twig',
        );

        self::assertStringContainsString(
            'bundles/polysourceeasyadminfilterbridge/' . self::CSS_FILENAME,
            $template,
        );
        self::assertStringContainsString(
            'bundles/polysourceeasyadminfilterbridge/' . self::SHIM_FILENAME,
            $template,
        );
    }

    public function testTemplatesCarryNoStaticInlineStyleOrScript(): void
    {
        $viewsDir = self::bridgeRoot() . '/Resources/views';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS),
        );
        $checked = 0;
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || 'twig' !== $file->getExtension()) {
                continue;
            }
            ++$checked;
            $content = (string) file_get_contents((string) $file);
            $relative = substr((string) $file, \strlen($viewsDir) + 1);

            $allowedStyleBlocks = 'crud/index.html.twig' === $relative ? 1 : 0;
            self::assertSame($allowedStyleBlocks, substr_count($content, '<style'), \sprintf(
                '%s must not carry static inline CSS (CSP contract) — move it to Resources/public/%s. '
                . 'Only the request-dependent hidden-columns block in crud/index.html.twig is allowed.',
                $relative,
                self::CSS_FILENAME,
            ));

            self::assertSame(0, substr_count($content, '<script>'), \sprintf(
                '%s must not carry inline JS (CSP contract) — move it to Resources/public/ '
                . 'and reference it with <script src="...">.',
                $relative,
            ));
        }

        self::assertGreaterThan(0, $checked, 'No Twig templates found — wrong views directory?');
    }
}

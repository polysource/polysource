<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Translation;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\KeyboardShortcutsExtension;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Locks the i18n contract of the `PolysourceEasyAdminFilterBridge`
 * translation domain:
 *
 * 1. Every locale catalog exposes the SAME key set — a key added to
 *    `en` without its `fr` counterpart (or vice versa) fails here
 *    instead of silently falling back to English in production.
 * 2. Every translation id referenced from PHP or Twig exists in the
 *    `en` catalog — catches typos in keys and keys removed from the
 *    catalog while still referenced.
 * 3. The dynamically-built shortcut keys
 *    (`polysource.shortcuts.action.*` / `.scope.*`) are resolved
 *    through a spy translator so the assertion follows the
 *    RECOMMENDED_SHORTCUTS table automatically.
 */
final class CatalogCompletenessTest extends TestCase
{
    /** @var list<string> */
    private const LOCALES = ['en', 'fr'];

    /**
     * @return array<string, string> flattened dot-notation key → message
     */
    private static function catalog(string $locale): array
    {
        $path = \dirname(__DIR__, 3)
            . '/Resources/translations/PolysourceEasyAdminFilterBridge.' . $locale . '.yaml';
        self::assertFileExists($path);

        /** @var array<string, mixed> $tree */
        $tree = Yaml::parseFile($path);

        $flat = [];
        $walk = static function (array $node, string $prefix) use (&$walk, &$flat): void {
            foreach ($node as $key => $value) {
                $id = '' === $prefix ? (string) $key : $prefix . '.' . $key;
                if (\is_array($value)) {
                    $walk($value, $id);
                } elseif (\is_scalar($value)) {
                    $flat[$id] = (string) $value;
                } else {
                    self::fail(\sprintf('Unexpected non-scalar leaf at "%s".', $id));
                }
            }
        };
        $walk($tree, '');

        return $flat;
    }

    public function testEveryLocaleExposesTheSameKeySet(): void
    {
        $reference = array_keys(self::catalog('en'));
        sort($reference);

        foreach (self::LOCALES as $locale) {
            $keys = array_keys(self::catalog($locale));
            sort($keys);

            self::assertSame($reference, $keys, \sprintf(
                'Catalog "%s" must expose exactly the same keys as "en" — '
                . 'missing keys silently fall back to English in production.',
                $locale,
            ));
        }
    }

    public function testEveryKeyReferencedInCodeExistsInTheCatalog(): void
    {
        $catalog = self::catalog('en');
        $bridgeRoot = \dirname(__DIR__, 3);

        $referenced = [];
        foreach (['/src', '/Resources/views'] as $dir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($bridgeRoot . $dir, FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !\in_array($file->getExtension(), ['php', 'twig'], true)) {
                    continue;
                }
                $content = (string) file_get_contents((string) $file);
                // Only ids at an actual translation call site — a bare
                // polysource.* literal can be a custom filter option
                // (`polysource.tab`), not a translation id. Dynamically
                // concatenated ids are covered by the spy-translator test.
                preg_match_all("/transWithFallback\\(\\s*'(polysource\\.[a-z0-9_.]+)'\\s*,/", $content, $phpMatches);
                preg_match_all("/'(polysource\\.[a-z0-9_.]+)'\\|trans\\(/", $content, $twigMatches);
                foreach ([...$phpMatches[1], ...$twigMatches[1]] as $id) {
                    $referenced[$id] = (string) $file;
                }
            }
        }

        self::assertNotEmpty($referenced, 'No polysource.* translation ids found — wrong scan roots?');

        foreach ($referenced as $id => $file) {
            self::assertArrayHasKey($id, $catalog, \sprintf(
                'Translation id "%s" (referenced in %s) is missing from the en catalog.',
                $id,
                $file,
            ));
        }
    }

    public function testShortcutTableKeysExistInTheCatalog(): void
    {
        $catalog = self::catalog('en');

        $spy = new class implements TranslatorInterface {
            /** @var list<string> */
            public array $requested = [];

            /**
             * @param array<array-key, mixed> $parameters
             */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $this->requested[] = $id;

                return $id;
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };

        $extension = new KeyboardShortcutsExtension($spy);
        $extension->shortcutsList();
        (string) $extension->renderHelp();

        self::assertNotEmpty($spy->requested);
        foreach (array_unique($spy->requested) as $id) {
            self::assertArrayHasKey($id, $catalog, \sprintf(
                'KeyboardShortcutsExtension requests translation id "%s" which is missing from the en catalog.',
                $id,
            ));
        }
    }

    public function testFrenchCatalogIsActuallyTranslated(): void
    {
        $en = self::catalog('en');
        $fr = self::catalog('fr');

        // Identical values are legitimate for a handful of cognates
        // (« Actions », « Normal », « Global », « Filtres »…) — but if
        // MOST messages are byte-identical to English, the fr catalog
        // is a copy-paste, not a translation.
        $identical = 0;
        foreach ($en as $id => $message) {
            if (($fr[$id] ?? null) === $message) {
                ++$identical;
            }
        }

        self::assertLessThan(
            (int) ceil(\count($en) / 3),
            $identical,
            'More than a third of the fr catalog is byte-identical to en — looks like untranslated copy-paste.',
        );
    }
}

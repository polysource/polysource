<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\KeyboardShortcutsExtension;
use Twig\TwigFunction;

#[CoversClass(KeyboardShortcutsExtension::class)]
final class KeyboardShortcutsExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheTwoShortcutFunctions(): void
    {
        $extension = new KeyboardShortcutsExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_keyboard_shortcuts_help', $names);
        self::assertContains('polysource_keyboard_shortcuts_list', $names);
        self::assertCount(2, $names);
    }

    #[Test]
    public function shortcutsListReturnsTheRecommendedSet(): void
    {
        $list = (new KeyboardShortcutsExtension())->shortcutsList();

        // The recommended set covers Listing, Search, Filters,
        // Columns, Actions, Help, Global scopes — verify a sample.
        $keys = array_column($list, 'key');
        self::assertContains('j', $keys);
        self::assertContains('k', $keys);
        self::assertContains('/', $keys);
        self::assertContains('?', $keys);
        self::assertContains('Esc', $keys);
    }

    #[Test]
    public function renderHelpEmitsANativeDetailsElement(): void
    {
        $html = (string) (new KeyboardShortcutsExtension())->renderHelp();

        self::assertStringContainsString('<details class="polysource-keyboard-shortcuts"', $html);
        self::assertStringContainsString('<summary>Keyboard shortcuts', $html);
        self::assertStringContainsString('<kbd>j</kbd>', $html);
        self::assertStringContainsString('<kbd>?</kbd>', $html);
    }

    #[Test]
    public function renderHelpEmitsTheHostShortcutsHook(): void
    {
        // The host's Stimulus controller binds to this data
        // attribute — keeping it stable across versions is part
        // of the contract.
        $html = (string) (new KeyboardShortcutsExtension())->renderHelp();

        self::assertStringContainsString('data-polysource-shortcuts-help', $html);
    }
}

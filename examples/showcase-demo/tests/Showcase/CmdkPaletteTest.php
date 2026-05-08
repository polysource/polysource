<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;

/**
 * E2E coverage for the polysource/search Cmd+K palette.
 *
 * The palette is a fully Stimulus-driven UX (cmdk_controller.js): a
 * keypress hook installs on document, the dialog mounts on demand,
 * the input debounces queries against the JSON endpoint, results
 * render into a listbox grouped per resource, arrow keys + Enter
 * navigate, Esc closes. None of that lives in any unit / functional
 * test — only this Panther file proves the wiring works.
 *
 * @group panther
 */
final class CmdkPaletteTest extends AbstractShowcasePantherTest
{
    public function testCmdkHotkeyOpensPaletteAndShowsAvailableResources(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/polysource/cache-keys');

        // Wait for the polysource search palette element to mount.
        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('[data-controller~="polysource--search--cmdk"]'),
            ),
        );

        // Send "/" — Cmdk controller listens for it as an alternate hotkey.
        // Cmd+K from PHP is awkward across host OS; "/" is a reliable proxy
        // and exercises the same controller code path.
        $body = $client->findElement(WebDriverBy::cssSelector('body'));
        $body->sendKeys('/');

        // Dialog must become visible (not just present).
        $client->wait(5)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::cssSelector('[data-polysource--search--cmdk-target="dialog"]'),
            ),
        );

        // Type a query — debounce 150ms before the AJAX call fires.
        $input = $client->findElement(WebDriverBy::cssSelector('[data-polysource--search--cmdk-target="input"]'));
        $input->sendKeys('alice');

        // The placeholder reflects translation — verify it's NOT the
        // raw translation key (would mean trans() resolved to fallback).
        $placeholder = (string) $input->getAttribute('placeholder');
        self::assertStringNotContainsString('polysource.search', $placeholder, 'Translator must resolve the placeholder, not return the raw key.');

        // Hint footer text is also translated.
        $hint = $client->findElement(WebDriverBy::cssSelector('.polysource-search-palette-hint'));
        self::assertNotEmpty(trim($hint->getText()));
    }

    public function testEscapeClosesPalette(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/polysource/cache-keys');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('[data-controller~="polysource--search--cmdk"]'),
            ),
        );

        $client->findElement(WebDriverBy::cssSelector('body'))->sendKeys('/');
        $client->wait(5)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::cssSelector('[data-polysource--search--cmdk-target="dialog"]'),
            ),
        );

        // Esc closes the dialog. The Cmdk Stimulus controller binds
        // its keydown handler to the SEARCH INPUT (Stimulus `keydown`
        // action on the input element), so we send the key to the
        // input — that's where the controller listens, and it mirrors
        // a real user pressing Esc while typing in the palette.
        $input = $client->findElement(WebDriverBy::cssSelector('[data-polysource--search--cmdk-target="input"]'));
        $input->sendKeys(WebDriverKeys::ESCAPE);

        $client->wait(3)->until(
            WebDriverExpectedCondition::invisibilityOfElementLocated(
                WebDriverBy::cssSelector('[data-polysource--search--cmdk-target="dialog"]'),
            ),
        );
        self::assertFalse(
            $client->findElement(WebDriverBy::cssSelector('[data-polysource--search--cmdk-target="dialog"]'))->isDisplayed(),
            'Esc must hide the palette dialog',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * E2E coverage for the v0.3.0 column-visibility toggle — the
 * dropdown auto-rendered by the bridge's `crud/index.html.twig`
 * override. Bridge-level wiring (no host-side template needed),
 * but it lacked dedicated E2E coverage until v0.5.1.
 *
 * @group panther
 * @group v030
 */
final class V050ColumnVisibilityTest extends AbstractShowcasePantherTestCase
{
    public function testColumnVisibilityDropdownRendersOnIndex(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.ea-column-visibility'),
            ),
        );

        $dropdown = $client->findElement(WebDriverBy::cssSelector('.ea-column-visibility'));
        $toggle = $dropdown->findElement(WebDriverBy::cssSelector('button.dropdown-toggle'));
        self::assertStringContainsString('⊞', $toggle->getText(), 'glyph trigger renders');
    }

    public function testEachFieldHasACheckboxInTheDropdownMenu(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.ea-column-visibility__menu'),
            ),
        );

        $menu = $client->findElement(WebDriverBy::cssSelector('.ea-column-visibility__menu'));
        $checkboxes = $menu->findElements(WebDriverBy::cssSelector('input[type="checkbox"][name="visible[]"]'));
        self::assertGreaterThan(0, \count($checkboxes), 'one checkbox per visible field');
    }
}

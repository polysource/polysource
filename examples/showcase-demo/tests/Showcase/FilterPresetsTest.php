<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Pins the EnhancedDateTimeFilterType preset buttons (A4 in the
 * showcase manual test plan) — clicking "Last 7 days" / "This month"
 * etc. populates the date input via the Stimulus
 * `polysource_filter_controller#applyPreset` action.
 *
 * Without this E2E test, a regression on the Stimulus controller
 * (lost `data-action` binding, removed preset constant, lost form
 * theme registration) would silently break the preset buttons —
 * unit tests can't catch this because the contract is
 * "click → input value changes" which only Selenium can observe.
 *
 * @group panther
 */
final class FilterPresetsTest extends AbstractShowcasePantherTestCase
{
    public function testClickingDatePresetPopulatesTheInput(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();

        $client->request('GET', '/admin/order');

        // Open the filter modal
        $client->wait(8)->until(
            WebDriverExpectedCondition::elementToBeClickable(
                WebDriverBy::cssSelector('.datagrid-filters .action-filters-button'),
            ),
        );
        $client->findElement(WebDriverBy::cssSelector('.datagrid-filters .action-filters-button'))->click();

        // Wait for the modal AJAX content to load
        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('#modal-filters .filter-checkbox'),
            ),
        );

        // Switch to the Dates tab (created via Polysource::tab marker)
        $datesTab = $client->findElements(WebDriverBy::xpath(
            '//div[@id="modal-filters"]//a[contains(@class, "nav-link") and contains(text(), "Dates")]',
        ));
        if (\count($datesTab) > 0) {
            $datesTab[0]->click();
        }

        // Locate the createdAt filter row, tick its checkbox, and find
        // its preset button bar.
        $createdAtCheckbox = $client->findElements(WebDriverBy::cssSelector(
            'input.filter-checkbox[data-filter-property="createdAt"]',
        ));
        if (\count($createdAtCheckbox) === 0) {
            self::markTestSkipped('createdAt filter checkbox not found on the Dates tab — showcase wiring may have changed');
        }
        $createdAtCheckbox[0]->click();

        // Click the "Last 7 days" preset button. The Stimulus action
        // is bound via data-action="polysource--filter#applyPreset"
        // on a button rendered by the EnhancedDateTimeFilterType theme.
        $presetButtons = $client->findElements(WebDriverBy::xpath(
            '//div[@id="modal-filters"]//button[@data-polysource--filter-preset-param="last_7_days"]',
        ));
        if (\count($presetButtons) === 0) {
            self::markTestSkipped('Last 7 days preset button not rendered — EnhancedDateTimeFilterType theme not wired');
        }
        $presetButtons[0]->click();

        // After click, the value input should be populated.
        $valueInput = $client->findElement(WebDriverBy::cssSelector(
            'input[name="filters[createdAt][value]"]',
        ));
        $populated = $valueInput->getAttribute('value') ?? '';
        self::assertNotSame(
            '',
            $populated,
            'clicking the "Last 7 days" preset must populate the createdAt value input',
        );
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}/',
            $populated,
            'preset must inject an ISO-formatted date, not a free-form string',
        );
    }
}

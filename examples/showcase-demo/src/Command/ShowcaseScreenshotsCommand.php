<?php

declare(strict_types=1);

namespace App\Command;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Panther\Client;

/**
 * Drives the running showcase via Selenium Chromium and captures
 * 16 page screenshots into docs/user/screenshots/.
 *
 * The command runs in a single Symfony process — the same process
 * authenticates via the real form, then navigates page by page.
 * No cookie sync magic needed (unlike Phase H's Panther tests).
 *
 * Outputs PNGs named `NN-slug.png` so they sort naturally when
 * embedded in the doc tour. Re-runnable; idempotent.
 */
#[AsCommand(
    name: 'app:showcase:screenshots',
    description: 'Capture all showcase screens into docs/user/screenshots/.',
)]
final class ShowcaseScreenshotsCommand extends Command
{
    private const VIEWPORT_WIDTH = 1440;
    private const VIEWPORT_HEIGHT = 900;

    /**
     * Sequenced journey — each entry produces one PNG.
     *
     * Per-entry contract:
     *   - `path`              relative to baseUri.
     *   - `wait`              CSS selector to wait for (lets dynamic content render).
     *   - `auth`              false ⇒ captured pre-login.
     *   - `click`             optional CSS selector clicked after the page settles.
     *   - `waitAfterClick`    CSS selector that MUST become VISIBLE (not just present)
     *                         after the click — used for modal/dropdown captures.
     *   - `assertMinRows`     sanity check: `<tbody>` must contain at least N
     *                         `<tr>` rows. Empty listings are a release blocker
     *                         — the run fails loud rather than ship empty PNGs.
     *
     * @var list<array{slug: string, path: string, wait?: string, auth?: bool, label?: string, click?: string, waitAfterClick?: string, assertMinRows?: int}>
     */
    private const JOURNEY = [
        // Anonymous.
        ['slug' => '01-login', 'path' => '/login', 'wait' => 'input[name="_username"]', 'auth' => false],
        // Authenticated home + EA CRUDs. Each EA index seeded with Foundry (1700 rows total).
        ['slug' => '02-home-dashboard', 'path' => '/', 'wait' => '.polysource-widget'],
        ['slug' => '03-easyadmin-products', 'path' => '/admin/product', 'wait' => 'table tbody tr', 'assertMinRows' => 5],
        ['slug' => '04-easyadmin-customers', 'path' => '/admin/customer', 'wait' => 'table tbody tr', 'assertMinRows' => 5],
        ['slug' => '05-easyadmin-orders', 'path' => '/admin/order', 'wait' => 'table tbody tr', 'assertMinRows' => 5],
        ['slug' => '06-easyadmin-refunds', 'path' => '/admin/refund', 'wait' => 'table tbody tr', 'assertMinRows' => 5],
        // Polysource standalone resources — non-Doctrine backends.
        ['slug' => '07-polysource-failed-messages', 'path' => '/admin/polysource/failed-messages', 'wait' => 'tbody tr', 'assertMinRows' => 5],
        ['slug' => '08-polysource-login-attempts', 'path' => '/admin/polysource/login-attempts', 'wait' => 'tbody tr', 'assertMinRows' => 5],
        ['slug' => '09-polysource-audit-log', 'path' => '/admin/polysource/audit-log', 'wait' => 'tbody tr', 'assertMinRows' => 5],
        ['slug' => '10-polysource-bulk-jobs', 'path' => '/admin/polysource/bulk-jobs', 'wait' => 'tbody tr', 'assertMinRows' => 1],
        // Phase E adapter-backed resources — Redis / MinIO / WireMock / Meilisearch.
        ['slug' => '11-polysource-cache-keys', 'path' => '/admin/polysource/cache-keys', 'wait' => 'tbody tr', 'assertMinRows' => 5],
        ['slug' => '12-polysource-s3-files', 'path' => '/admin/polysource/s3-files', 'wait' => 'tbody tr', 'assertMinRows' => 5],
        ['slug' => '13-polysource-microservices', 'path' => '/admin/polysource/microservices', 'wait' => 'tbody tr', 'assertMinRows' => 1],
        ['slug' => '14-polysource-search-index', 'path' => '/admin/polysource/search-index', 'wait' => 'tbody tr', 'assertMinRows' => 5],
        // Feature deep-dives — captured AFTER an interaction. `waitAfterClick`
        // pins the post-click visible state; if the modal/dropdown doesn't open
        // we fail loud rather than ship a blank capture.
        ['slug' => '15-saved-views-dropdown-open', 'path' => '/admin/order', 'wait' => '.polysource-saved-views', 'click' => '.polysource-saved-views .dropdown-toggle', 'waitAfterClick' => '.polysource-saved-views .dropdown-menu.show'],
        // Filter modal: wait for `.show` (visible) AND a content selector
        // injected by the AJAX `/admin/<resource>/render-filters` call —
        // otherwise we capture the modal mid-load (Bootstrap spinner only).
        ['slug' => '16-filters-modal-tabs', 'path' => '/admin/order', 'wait' => '[data-bs-target="#modal-filters"]', 'click' => '[data-bs-target="#modal-filters"]', 'waitAfterClick' => '#modal-filters.show .filter-field'],

        // === v0.3.0 → v0.5.0 captures ===
        // Auto-rendered by the bridge + Sprint A/B showcase wiring.

        // v0.3.0 #11 — Column visibility toggle dropdown.
        ['slug' => '17-column-visibility-toggle', 'path' => '/admin/order', 'wait' => '.ea-column-visibility', 'click' => '.ea-column-visibility .dropdown-toggle', 'waitAfterClick' => '.ea-column-visibility__menu.show', 'assertMinRows' => 5],

        // v0.3.0 #14 — Row class colouring (filter to paid for the table-info class to appear).
        ['slug' => '18-row-conditional-styles', 'path' => '/admin/order?filters%5Bstatus%5D%5Bcomparison%5D=%3D&filters%5Bstatus%5D%5Bvalue%5D%5B0%5D=paid', 'wait' => 'tbody tr.table-info', 'assertMinRows' => 1],

        // v0.4.0 #16 — Cell filter menu dropdown.
        ['slug' => '19-cell-filter-menu', 'path' => '/admin/order', 'wait' => '.polysource-cell-filter-menu', 'click' => '.polysource-cell-filter-menu__trigger', 'waitAfterClick' => '.polysource-cell-filter-menu__list.show', 'assertMinRows' => 5],

        // v0.4.0 #17 — Quick filter row.
        ['slug' => '20-quick-filter-row', 'path' => '/admin/order', 'wait' => 'tr.polysource-quick-filter-row input', 'assertMinRows' => 5],

        // v0.4.0 #19 — Bulk scope toggle (rendered inline in showcase, not behind row-selection JS).
        ['slug' => '21-bulk-scope-toggle', 'path' => '/admin/order', 'wait' => 'input[name="bulk_scope"]', 'assertMinRows' => 5],

        // v0.5.0 #2 — Frozen columns (visible in standard view).
        ['slug' => '22-frozen-columns', 'path' => '/admin/order', 'wait' => 'thead .polysource-frozen-column', 'assertMinRows' => 5],

        // v0.5.0 #3 — Row density compact mode.
        ['slug' => '23-density-compact', 'path' => '/admin/order?density=compact', 'wait' => 'table.table-sm', 'assertMinRows' => 5],

        // v0.5.0 #5 — Keyboard shortcuts cheat sheet (opened).
        ['slug' => '24-kbd-shortcuts', 'path' => '/admin/order', 'wait' => '.polysource-keyboard-shortcuts', 'click' => '.polysource-keyboard-shortcuts > summary', 'waitAfterClick' => '.polysource-keyboard-shortcuts__table'],

        // v0.5.0 #7 — Filter share button (visible only with active filters).
        ['slug' => '25-filter-share-button', 'path' => '/admin/order?filters%5Bstatus%5D%5Bcomparison%5D=%3D&filters%5Bstatus%5D%5Bvalue%5D%5B0%5D=paid', 'wait' => '.polysource-filter-share', 'assertMinRows' => 1],

        // v0.5.0 #1 — Column reorder buttons (anchor pairs).
        ['slug' => '26-column-reorder-buttons', 'path' => '/admin/order', 'wait' => '.polysource-column-reorder', 'assertMinRows' => 5],
    ];

    /** @var list<string> Warnings collected during the run; printed at the end + return non-zero. */
    private array $warnings = [];

    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Email to log in as.', 'admin@shop.co')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for the user.', 'shopco')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Output directory.', null)
            ->addOption('selenium-url', null, InputOption::VALUE_REQUIRED, 'Selenium grid URL.', 'http://chrome:4444/wd/hub')
            ->addOption('base-uri', null, InputOption::VALUE_REQUIRED, 'Showcase base URI from chrome.', 'http://nginx');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $outputDir = (string) ($input->getOption('output-dir')
            ?: $this->projectDir . '/../../docs/user/screenshots');
        $fs = new Filesystem();
        $fs->mkdir($outputDir);

        $email = (string) $input->getOption('user');
        $password = (string) $input->getOption('password');

        $io->title('Polysource Showcase — screenshots pipeline');
        $io->writeln(\sprintf(' • Output dir : %s', realpath($outputDir) ?: $outputDir));
        $io->writeln(\sprintf(' • Login as   : %s', $email));
        $io->writeln(\sprintf(' • Selenium   : %s', $input->getOption('selenium-url')));
        $io->writeln(\sprintf(' • Base URI   : %s', $input->getOption('base-uri')));
        $io->newLine();

        $client = $this->createBrowser((string) $input->getOption('selenium-url'), (string) $input->getOption('base-uri'));

        try {
            // 1. Pre-auth captures.
            $this->captureAnonymousPages($client, $outputDir, $io);

            // 2. Authenticate via the real form — single process so no
            // cookie-sync gymnastics needed.
            $this->loginViaForm($client, $email, $password);

            // 3. Authenticated captures.
            $this->captureAuthenticatedPages($client, $outputDir, $io);
        } finally {
            $client->quit();
        }

        if ($this->warnings !== []) {
            $io->newLine();
            $io->error(\sprintf('%d capture(s) flagged as suspect — DO NOT publish these screenshots:', \count($this->warnings)));
            foreach ($this->warnings as $warning) {
                $io->writeln(' • ' . $warning);
            }
            $io->newLine();
            $io->writeln('<comment>Common causes:</comment>');
            $io->writeln('  - Fixtures not loaded → run <info>make fixtures</info> then re-run.');
            $io->writeln('  - Modal Stimulus controller failed to boot → check JS console in dev tools.');
            $io->writeln('  - Wrong selector after a UI refactor → update the JOURNEY entry.');

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d screenshots written to %s', \count(self::JOURNEY), $outputDir));

        return Command::SUCCESS;
    }

    private function createBrowser(string $seleniumUrl, string $baseUri): Client
    {
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability('goog:chromeOptions', [
            'args' => [
                '--headless=new',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                \sprintf('--window-size=%d,%d', self::VIEWPORT_WIDTH, self::VIEWPORT_HEIGHT),
            ],
        ]);

        $client = Client::createSeleniumClient(
            host: $seleniumUrl,
            capabilities: $capabilities,
            baseUri: $baseUri,
        );
        $client->manage()->window()->setSize(new WebDriverDimension(self::VIEWPORT_WIDTH, self::VIEWPORT_HEIGHT));

        return $client;
    }

    private function loginViaForm(Client $client, string $email, string $password): void
    {
        $client->request('GET', '/login');
        $client->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::name('_username'),
            ),
        );

        $client->findElement(WebDriverBy::name('_username'))->sendKeys($email);
        $client->findElement(WebDriverBy::name('_password'))->sendKeys($password);
        $client->executeScript('document.querySelector("form").submit();');

        // Wait for the firewall redirect to land on the home dashboard.
        $client->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-widget'),
            ),
        );
    }

    private function captureAnonymousPages(Client $client, string $outputDir, SymfonyStyle $io): void
    {
        foreach (self::JOURNEY as $page) {
            if (($page['auth'] ?? true) === true) {
                continue;
            }
            $this->capture($client, $outputDir, $page, $io);
        }
    }

    private function captureAuthenticatedPages(Client $client, string $outputDir, SymfonyStyle $io): void
    {
        foreach (self::JOURNEY as $page) {
            if (($page['auth'] ?? true) === false) {
                continue;
            }
            $this->capture($client, $outputDir, $page, $io);
        }
    }

    /**
     * @param array{slug: string, path: string, wait?: string, auth?: bool, label?: string, click?: string, waitAfterClick?: string, assertMinRows?: int} $page
     */
    private function capture(Client $client, string $outputDir, array $page, SymfonyStyle $io): void
    {
        $client->request('GET', $page['path']);
        if (isset($page['wait'])) {
            try {
                $client->wait(8)->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::cssSelector($page['wait']),
                    ),
                );
            } catch (\Facebook\WebDriver\Exception\TimeoutException) {
                $this->warnings[] = \sprintf(
                    '%s — wait selector "%s" never appeared on %s (page likely empty or rendering broken)',
                    $page['slug'],
                    $page['wait'],
                    $page['path'],
                );
            }
        }

        // Sanity assertion: listing pages MUST have at least N rows.
        // Capturing an empty `<tbody>` yields a screenshot that falsely
        // documents a broken feature — we'd rather fail the run loud.
        if (isset($page['assertMinRows'])) {
            $rowCount = \count($client->findElements(WebDriverBy::cssSelector('tbody tr')));
            if ($rowCount < $page['assertMinRows']) {
                $this->warnings[] = \sprintf(
                    '%s — only %d row(s) in <tbody> (expected >= %d). Resource backend is likely empty: re-run seeds.',
                    $page['slug'],
                    $rowCount,
                    $page['assertMinRows'],
                );
            }
        }

        // Optional: click an element after the page settles (used to
        // open the saved-views dropdown / filter modal so the screenshot
        // captures the feature *in action*).
        if (isset($page['click'])) {
            $clickOk = $this->performClick($client, $page);
            if ($clickOk && isset($page['waitAfterClick'])) {
                $this->waitForVisibleAfterClick($client, $page);
            }
        }

        // Tiny settle delay so any lazy-loaded images (Lucide icons,
        // Bootstrap fonts) finish before the capture.
        usleep(300_000);

        $path = $outputDir . '/' . $page['slug'] . '.png';
        $client->takeScreenshot($path);

        $io->writeln(\sprintf(' ✓ <info>%s</info>  →  %s', $page['slug'], $page['path']));
    }

    /**
     * @param array{slug: string, click?: string} $page
     */
    private function performClick(Client $client, array $page): bool
    {
        if (!isset($page['click'])) {
            return false;
        }
        try {
            $element = $client->findElement(WebDriverBy::cssSelector($page['click']));
            // Scroll the target into view before clicking — Chromium
            // refuses to click on elements outside the viewport with
            // "element click intercepted" errors. Captures like the
            // keyboard-shortcut cheat sheet (rendered at the bottom
            // of the page) need this.
            $client->executeScript(
                'arguments[0].scrollIntoView({behavior: "instant", block: "center"});',
                [$element],
            );
            // Tiny settle so the scroll animation completes before the click.
            usleep(200_000);
            $element->click();

            return true;
        } catch (\Facebook\WebDriver\Exception\NoSuchElementException) {
            $this->warnings[] = \sprintf(
                '%s — click target "%s" not found in DOM (selector outdated after a UI refactor?)',
                $page['slug'],
                $page['click'],
            );

            return false;
        }
    }

    /**
     * Modal / dropdown captures need the post-click element to be
     * VISIBLE, not just present in the DOM (Bootstrap modals exist in
     * the DOM with `display:none` until `.show` is added). Use
     * `visibilityOfElementLocated` so a stuck-hidden modal raises a
     * timeout we can flag rather than silently capturing the trigger.
     *
     * @param array{slug: string, waitAfterClick?: string} $page
     */
    private function waitForVisibleAfterClick(Client $client, array $page): void
    {
        if (!isset($page['waitAfterClick'])) {
            return;
        }
        try {
            $client->wait(8)->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(
                    WebDriverBy::cssSelector($page['waitAfterClick']),
                ),
            );
        } catch (\Facebook\WebDriver\Exception\TimeoutException) {
            $this->warnings[] = \sprintf(
                '%s — post-click selector "%s" never became visible. Modal/dropdown likely failed to open (Stimulus boot? Bootstrap JS missing?).',
                $page['slug'],
                $page['waitAfterClick'],
            );
        }
    }
}

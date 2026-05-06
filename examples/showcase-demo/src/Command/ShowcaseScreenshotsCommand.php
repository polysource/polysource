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
     * `path` is relative to baseUri. `wait` is a CSS selector to wait
     * for before capturing (lets dynamic content render). `auth=false`
     * captures pre-login pages.
     *
     * @var list<array{slug: string, path: string, wait?: string, auth?: bool, label?: string}>
     */
    private const JOURNEY = [
        // Anonymous.
        ['slug' => '01-login', 'path' => '/login', 'wait' => 'input[name="_username"]', 'auth' => false],
        // Authenticated home + EA CRUDs.
        ['slug' => '02-home-dashboard', 'path' => '/', 'wait' => '.polysource-widget'],
        ['slug' => '03-easyadmin-products', 'path' => '/admin/product', 'wait' => 'table tbody tr'],
        ['slug' => '04-easyadmin-customers', 'path' => '/admin/customer', 'wait' => 'table tbody tr'],
        ['slug' => '05-easyadmin-orders', 'path' => '/admin/order', 'wait' => 'table tbody tr'],
        ['slug' => '06-easyadmin-refunds', 'path' => '/admin/refund', 'wait' => 'table tbody tr'],
        // Polysource standalone resources.
        ['slug' => '07-polysource-failed-messages', 'path' => '/admin/polysource/failed-messages', 'wait' => 'h1'],
        ['slug' => '08-polysource-login-attempts', 'path' => '/admin/polysource/login-attempts', 'wait' => 'h1'],
        ['slug' => '09-polysource-audit-log', 'path' => '/admin/polysource/audit-log', 'wait' => 'h1'],
        ['slug' => '10-polysource-bulk-jobs', 'path' => '/admin/polysource/bulk-jobs', 'wait' => 'h1'],
        // Phase E adapter-backed resources.
        ['slug' => '11-polysource-cache-keys', 'path' => '/admin/polysource/cache-keys', 'wait' => 'h1'],
        ['slug' => '12-polysource-s3-files', 'path' => '/admin/polysource/s3-files', 'wait' => 'h1'],
        ['slug' => '13-polysource-microservices', 'path' => '/admin/polysource/microservices', 'wait' => 'h1'],
        ['slug' => '14-polysource-search-index', 'path' => '/admin/polysource/search-index', 'wait' => 'h1'],
        // Feature deep-dives — captured AFTER an interaction.
        ['slug' => '15-saved-views-dropdown-open', 'path' => '/admin/order', 'wait' => '.polysource-saved-views', 'click' => '.polysource-saved-views .dropdown-toggle'],
        ['slug' => '16-filters-modal-tabs', 'path' => '/admin/order', 'wait' => '[data-bs-target="#modal-filters"]', 'click' => '[data-bs-target="#modal-filters"]', 'waitAfterClick' => '#modal-filters .nav-tabs'],
    ];

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
            ?: $this->projectDir.'/../../docs/user/screenshots');
        $fs = new Filesystem();
        $fs->mkdir($outputDir);

        $email = (string) $input->getOption('user');
        $password = (string) $input->getOption('password');

        $io->title('Polysource Showcase — screenshots pipeline');
        $io->writeln(sprintf(' • Output dir : %s', realpath($outputDir) ?: $outputDir));
        $io->writeln(sprintf(' • Login as   : %s', $email));
        $io->writeln(sprintf(' • Selenium   : %s', $input->getOption('selenium-url')));
        $io->writeln(sprintf(' • Base URI   : %s', $input->getOption('base-uri')));
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

        $io->success(sprintf('%d screenshots written to %s', \count(self::JOURNEY), $outputDir));

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
                sprintf('--window-size=%d,%d', self::VIEWPORT_WIDTH, self::VIEWPORT_HEIGHT),
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
     * @param array{slug: string, path: string, wait?: string, auth?: bool, label?: string, click?: string} $page
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
                // Fall through — capture whatever rendered (the page
                // might be empty by design, e.g. a polysource resource
                // with no rows). The PNG will document the empty state.
            }
        }

        // Optional: click an element after the page settles (used to
        // open the saved-views dropdown / filter modal so the screenshot
        // captures the feature *in action*).
        if (isset($page['click'])) {
            try {
                $element = $client->findElement(WebDriverBy::cssSelector($page['click']));
                $element->click();

                if (isset($page['waitAfterClick'])) {
                    try {
                        $client->wait(8)->until(
                            WebDriverExpectedCondition::presenceOfElementLocated(
                                WebDriverBy::cssSelector($page['waitAfterClick']),
                            ),
                        );
                    } catch (\Facebook\WebDriver\Exception\TimeoutException) {
                        usleep(2_000_000);
                        // Diagnostic: dump what's in the modal-body so
                        // we know if the controller ran at all.
                        // No diagnostic — usage was for debugging the
                        // stimulus_bootstrap loading on EA pages, fixed
                        // by Dashboard::configureAssets().
                    }
                } else {
                    usleep(2_500_000);
                }
            } catch (\Facebook\WebDriver\Exception\NoSuchElementException) {
                // Element absent — capture the page as-is.
            }
        }

        // Tiny settle delay so any lazy-loaded images (Lucide icons,
        // Bootstrap fonts) finish before the capture.
        usleep(300_000);

        $path = $outputDir.'/'.$page['slug'].'.png';
        $client->takeScreenshot($path);

        $io->writeln(sprintf(' ✓ <info>%s</info>  →  %s', $page['slug'], $page['path']));
    }
}

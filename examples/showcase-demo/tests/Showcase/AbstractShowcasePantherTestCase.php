<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Throwable;

/**
 * Base class for Panther E2E tests + the Phase I screenshot pipeline.
 *
 * Connects to the dockerised Selenium standalone Chromium (`chrome`
 * service in docker-compose.yml, profile `e2e`) and points the browser
 * at the host-internal nginx URL so the test stays inside the
 * container network.
 *
 * Run with `make panther` — the make target spins the chrome service
 * up via `docker compose --profile e2e up -d` before invoking phpunit.
 *
 * **Login**: Symfony 7's stateless CSRF + Turbo prefetch make
 * traditional form-driven login flaky in Panther/Selenium. Instead we
 * boot a Symfony test kernel, mint a session for the wanted user via
 * `KernelBrowser::loginUser`, then transplant the session cookie into
 * the Panther/Selenium browser. One round-trip, no race, no JS hooks.
 */
abstract class AbstractShowcasePantherTestCase extends PantherTestCase
{
    protected static ?Client $browser = null;

    protected function browser(): Client
    {
        if (self::$browser === null) {
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability('goog:chromeOptions', [
                'args' => [
                    '--headless=new',
                    '--no-sandbox',
                    '--disable-dev-shm-usage',
                    '--window-size=1440,900',
                ],
            ]);

            self::$browser = Client::createSeleniumClient(
                host: $_SERVER['PANTHER_SELENIUM_REMOTE_URL'] ?? 'http://chrome:4444/wd/hub',
                capabilities: $capabilities,
                baseUri: $_SERVER['PANTHER_EXTERNAL_BASE_URI'] ?? 'http://nginx',
            );
        }

        return self::$browser;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$browser !== null) {
            self::$browser->quit();
            self::$browser = null;
        }
        parent::tearDownAfterClass();
    }

    /**
     * Authenticate by submitting the real login form. Robust across
     * test/dev kernels (no shared session storage required) and exercises
     * the same auth path a real user would.
     */
    protected function loginViaForm(string $email, string $password = 'shopco'): void
    {
        $client = $this->browser();
        $client->request('GET', '/login');

        // Idempotent: PantherTestCase shares the browser across tests
        // in the same class, so subsequent calls land on a page that
        // doesn't have the login form (already authenticated, redirected
        // away). Bail early when no form fields appear.
        // Probe for the form. The condition can raise either
        // TimeoutException (wait expired with falsy result) or
        // NoSuchElementException (element absent at probe time)
        // depending on Selenium server version. Treat both as
        // "already logged in".
        $hasForm = false;
        try {
            $hasForm = !empty($client->findElements(\Facebook\WebDriver\WebDriverBy::name('_username')));
        } catch (Throwable) {
            $hasForm = false;
        }
        if (!$hasForm) {
            return;
        }

        $client->findElement(\Facebook\WebDriver\WebDriverBy::name('_username'))->sendKeys($email);
        $client->findElement(\Facebook\WebDriver\WebDriverBy::name('_password'))->sendKeys($password);
        $client->executeScript('document.querySelector("form").submit();');

        // Land on the authenticated home dashboard. If credentials are
        // wrong, we'd bounce back to /login with an error flash —
        // assert we did NOT bounce so failures surface loud.
        $client->wait(10)->until(
            \Facebook\WebDriver\WebDriverExpectedCondition::not(
                \Facebook\WebDriver\WebDriverExpectedCondition::urlContains('/login'),
            ),
        );
    }

    /**
     * @deprecated KernelBrowser session-cookie transplant turned out to
     *             be flaky against the live Redis-backed sessions; use
     *             {@see self::loginViaForm()} instead. Kept for
     *             compatibility with older tests.
     */
    protected function loginAs(string $email): void
    {
        $client = $this->browser();
        $client->request('GET', '/'); // ensures cookie domain matches

        $kernel = self::bootShowcaseKernel();
        $container = $kernel->getContainer();

        $kernelBrowser = new KernelBrowser($kernel);
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $hasher = $container->get(UserPasswordHasherInterface::class);
            $user = (new User())
                ->setEmail($email)
                ->setFirstName(ucfirst(explode('@', $email)[0]))
                ->setLastName('Demo')
                ->setRoles([match ($email) {
                    'admin@shop.co' => 'ROLE_ADMIN',
                    'ops@shop.co' => 'ROLE_OPS',
                    default => 'ROLE_VIEWER',
                }]);
            $user->setPassword($hasher->hashPassword($user, 'shopco'));
            $em->persist($user);
            $em->flush();
        }

        $kernelBrowser->loginUser($user);
        // Make a real request through the kernel — that's what flushes
        // the session to the Redis backend. Without it, the cookie jar
        // would only hold a session ID that never gets persisted, and
        // the live app would reject the cookie on the next visit.
        $kernelBrowser->request('GET', '/');
        $cookieJar = $kernelBrowser->getCookieJar();

        // Push session cookies to Selenium via WebDriver manage().
        // Panther's getCookieJar() only mirrors internally — manage()
        // is what propagates to the live browser session. Cookie
        // domain must match the browser-visible host (nginx) for
        // Chromium to send it back on subsequent requests.
        foreach ($cookieJar->all() as $cookie) {
            $wdCookie = new \Facebook\WebDriver\Cookie(
                $cookie->getName(),
                $cookie->getValue(),
            );
            $wdCookie->setDomain('nginx');
            $wdCookie->setPath('/');
            $client->manage()->addCookie($wdCookie);
        }

        $kernel->shutdown();
    }

    /**
     * Boot a fresh Showcase kernel to mint sessions. We can't reuse
     * the kernel started by PantherTestCase because it would step on
     * the running dev server's session storage.
     */
    private static function bootShowcaseKernel(): \Symfony\Component\HttpKernel\KernelInterface
    {
        // Symfony's WebTestCase bridge is the cleanest way to bootstrap
        // a kernel + test client wired with the same DB / security
        // configuration as the dev server.
        return KernelTestCase::bootKernel();
    }
}

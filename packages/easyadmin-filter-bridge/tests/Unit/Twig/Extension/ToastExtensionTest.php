<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ToastExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\TwigFunction;

#[CoversClass(ToastExtension::class)]
final class ToastExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheToastsFunction(): void
    {
        $extension = new ToastExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertSame(['polysource_toasts'], $names);
    }

    #[Test]
    public function rendersNothingWithoutASession(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/admin/orders'));
        $extension = new ToastExtension($stack);

        self::assertSame('', (string) $extension->render());
    }

    #[Test]
    public function rendersNothingWhenFlashBagIsEmpty(): void
    {
        $extension = $this->buildWithFlashes([]);

        self::assertSame('', (string) $extension->render());
    }

    #[Test]
    public function rendersSuccessFlashAsBootstrapSuccessAlert(): void
    {
        $extension = $this->buildWithFlashes(['success' => ['Saved 42 rows.']]);

        $html = (string) $extension->render();

        self::assertStringContainsString('alert-success', $html);
        self::assertStringContainsString('Saved 42 rows.', $html);
        self::assertStringContainsString('polysource-toast-container', $html);
        self::assertStringContainsString('top-0 end-0', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
    }

    #[Test]
    public function mapsErrorFlashToDangerAlert(): void
    {
        $extension = $this->buildWithFlashes(['error' => ['Something blew up.']]);

        $html = (string) $extension->render();

        self::assertStringContainsString('alert-danger', $html);
        self::assertStringContainsString('Something blew up.', $html);
    }

    #[Test]
    public function mapsDangerFlashToDangerAlert(): void
    {
        $extension = $this->buildWithFlashes(['danger' => ['Mayday.']]);

        $html = (string) $extension->render();

        self::assertStringContainsString('alert-danger', $html);
    }

    #[Test]
    public function mapsWarningFlashToWarningAlert(): void
    {
        $extension = $this->buildWithFlashes(['warning' => ['Heads up.']]);

        $html = (string) $extension->render();

        self::assertStringContainsString('alert-warning', $html);
    }

    #[Test]
    public function unknownFlashTypeFallsBackToInfoAlert(): void
    {
        $extension = $this->buildWithFlashes(['custom' => ['Hello.']]);

        $html = (string) $extension->render();

        self::assertStringContainsString('alert-info', $html);
        self::assertStringContainsString('Hello.', $html);
    }

    #[Test]
    public function rendersMultipleAlertsInOrder(): void
    {
        $extension = $this->buildWithFlashes([
            'success' => ['First.', 'Second.'],
            'error' => ['Third.'],
        ]);

        $html = (string) $extension->render();

        $firstPos = strpos($html, 'First.');
        $secondPos = strpos($html, 'Second.');
        $thirdPos = strpos($html, 'Third.');

        self::assertNotFalse($firstPos);
        self::assertNotFalse($secondPos);
        self::assertNotFalse($thirdPos);
        self::assertLessThan($secondPos, $firstPos);
        self::assertLessThan($thirdPos, $secondPos);
    }

    #[Test]
    public function escapesHtmlInFlashMessages(): void
    {
        $extension = $this->buildWithFlashes(['success' => ['<script>alert("xss")</script>']]);

        $html = (string) $extension->render();

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @param array<string, list<string>> $flashes
     */
    private function buildWithFlashes(array $flashes): ToastExtension
    {
        $session = new Session(new MockArraySessionStorage());
        foreach ($flashes as $type => $messages) {
            foreach ($messages as $message) {
                $session->getFlashBag()->add($type, $message);
            }
        }
        $request = Request::create('/admin/orders');
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        return new ToastExtension($stack);
    }
}

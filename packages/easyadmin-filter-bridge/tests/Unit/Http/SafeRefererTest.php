<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Http\SafeReferer;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(SafeReferer::class)]
final class SafeRefererTest extends TestCase
{
    #[Test]
    public function preservesSameHostAbsoluteUrl(): void
    {
        $request = $this->requestFromHost('admin.example.com', referer: 'https://admin.example.com/orders?page=2');

        self::assertSame('https://admin.example.com/orders?page=2', SafeReferer::resolve($request));
    }

    #[Test]
    public function preservesRelativePathReferer(): void
    {
        // Relative paths have no host — by definition same-origin.
        $request = $this->requestFromHost('admin.example.com', referer: '/orders?page=2');

        self::assertSame('/orders?page=2', SafeReferer::resolve($request));
    }

    #[Test]
    public function rejectsExternalHostReferer(): void
    {
        $request = $this->requestFromHost('admin.example.com', referer: 'https://evil.com/phish');

        self::assertSame('/', SafeReferer::resolve($request));
    }

    #[Test]
    public function rejectsSubdomainAsExternalHost(): void
    {
        // attacker.example.com is technically a sibling subdomain.
        // SafeReferer requires exact-host match so this is rejected.
        $request = $this->requestFromHost('admin.example.com', referer: 'https://attacker.example.com/x');

        self::assertSame('/', SafeReferer::resolve($request));
    }

    #[Test]
    public function fallsBackWhenNoRefererHeader(): void
    {
        $request = $this->requestFromHost('admin.example.com', referer: null);

        self::assertSame('/', SafeReferer::resolve($request));
    }

    #[Test]
    public function fallsBackWhenRefererIsEmptyString(): void
    {
        $request = $this->requestFromHost('admin.example.com', referer: '');

        self::assertSame('/', SafeReferer::resolve($request));
    }

    #[Test]
    public function fallsBackToCustomDefault(): void
    {
        $request = $this->requestFromHost('admin.example.com', referer: 'https://evil.com/x');

        self::assertSame('/admin', SafeReferer::resolve($request, '/admin'));
    }

    #[Test]
    public function rejectsSchemelessExternalUrlThatLooksRelative(): void
    {
        // `//evil.com/x` is a protocol-relative URL — resolves against
        // current scheme but jumps to evil.com. SafeReferer treats it
        // as relative-path-only when parse_url returns no host, but
        // parse_url DOES return host for `//evil.com/x`.
        $request = $this->requestFromHost('admin.example.com', referer: '//evil.com/x');

        self::assertSame('/', SafeReferer::resolve($request));
    }

    private function requestFromHost(string $host, ?string $referer): Request
    {
        $request = Request::create('https://' . $host . '/admin', 'POST');
        if (null !== $referer) {
            $request->headers->set('referer', $referer);
        }

        return $request;
    }
}

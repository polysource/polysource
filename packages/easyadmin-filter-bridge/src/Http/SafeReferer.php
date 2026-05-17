<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Same-host referer validator for controllers that redirect back to
 * whichever URL the user came from. Without validation the `Referer`
 * header is attacker-controllable — a malicious link could submit
 * a state-mutating POST that then redirects the user to an external
 * site under the attacker's control (open-redirect chain).
 *
 * Returns the referer when it points to the same host as the current
 * request; otherwise the supplied fallback path. The path-only
 * comparison handles both http and https variants and ignores ports
 * to keep dev environments working behind reverse proxies (Symfony
 * already terminates SSL at the proxy in the typical setup).
 *
 * @since 0.9.0
 */
final class SafeReferer
{
    /**
     * Resolve a safe redirect target for the current request.
     *
     * @param string $fallback path to use when the referer is missing
     *                         or points to a different host; defaults
     *                         to `/`. Hosts pass `/admin` etc. when
     *                         they know their EA mount point.
     */
    public static function resolve(Request $request, string $fallback = '/'): string
    {
        $referer = $request->headers->get('referer');
        if (!\is_string($referer) || '' === $referer) {
            return $fallback;
        }

        $refererHost = parse_url($referer, \PHP_URL_HOST);
        if (!\is_string($refererHost) || '' === $refererHost) {
            // Relative referer (path-only) — same-origin by definition.
            return str_starts_with($referer, '/') ? $referer : $fallback;
        }

        return $refererHost === $request->getHost() ? $referer : $fallback;
    }
}

<?php

declare(strict_types=1);

namespace App\Story;

use App\Entity\LoginAttempt;
use App\Factory\LoginAttemptFactory;
use DateTimeImmutable;
use Zenstruck\Foundry\Story;

/**
 * 300 login attempts spread over 30 days.
 *
 * Adds a "credential-stuffing burst" pattern: 25 consecutive
 * bad_credentials hits from the same IP within a 5-minute window —
 * gives the ops viewer something obvious to find when filtering by
 * ip + status in the Polysource login-attempts resource.
 */
final class LoginAttemptsStory extends Story
{
    public function build(): void
    {
        // Base population — 275 random attempts.
        LoginAttemptFactory::createMany(275);

        // Brute-force burst — same IP, same victim, 25 hits in 5 minutes.
        $burstStart = new DateTimeImmutable('-3 days 14:00:00');
        $burstIp = '203.0.113.42';
        $victim = 'admin@shop.co';

        for ($i = 0; $i < 25; ++$i) {
            LoginAttemptFactory::createOne([
                'email' => $victim,
                'ip' => $burstIp,
                'userAgent' => 'curl/8.4.0',
                'status' => $i === 24
                    ? LoginAttempt::STATUS_RATE_LIMITED
                    : LoginAttempt::STATUS_BAD_CREDENTIALS,
                'occurredAt' => $burstStart->modify('+' . ($i * 12) . ' seconds'),
            ]);
        }
    }
}

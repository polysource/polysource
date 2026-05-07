<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\RetryPaymentWebhook;
use App\Message\SendOrderConfirmationEmail;
use App\Message\SyncProductToMeilisearch;
use DateTimeImmutable;
use DomainException;
use LogicException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Seed the `failed` Messenger transport with 50 realistic envelopes
 * across 3 message classes:
 *  - SendOrderConfirmationEmail (SMTP errors)
 *  - SyncProductToMeilisearch   (HTTP 5xx / connection refused)
 *  - RetryPaymentWebhook        (timeout / declined / DNS)
 *
 * Bypasses the bus + worker on purpose — the failure transport
 * receives Envelopes that LOOK like Symfony's
 * SendFailedMessageToFailureTransportListener already pushed them,
 * so the seed completes in a few hundred ms instead of waiting on
 * 3×retries × 50 messages = 150 retry attempts.
 */
#[AsCommand(
    name: 'app:seed-failed-messages',
    description: 'Seed the failed transport with 50 realistic ShopCo envelopes.',
)]
final class SeedFailedMessagesCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.failed')]
        private readonly TransportInterface $failedTransport,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $envelopes = $this->buildEnvelopes();
        foreach ($envelopes as $envelope) {
            $this->failedTransport->send($envelope);
        }

        $io->success(\sprintf(
            '%d envelopes seeded onto the failed transport.',
            \count($envelopes),
        ));
        $io->note('Browse them at /admin/polysource/failed-messages.');

        return Command::SUCCESS;
    }

    /**
     * @return list<Envelope>
     */
    private function buildEnvelopes(): array
    {
        $envelopes = [];

        // 22 email failures — bulk of failed transport in real ops.
        $emailFailures = [
            ['SMTP rate limit hit (5/min)', LogicException::class, 1],
            ['Connection refused on smtp.mailgun.org:587', RuntimeException::class, 0],
            ['SMTP greeting timed out', RuntimeException::class, 2],
            ['Recipient address rejected: domain not found', DomainException::class, 3],
            ['TLS handshake failed: certificate expired', RuntimeException::class, 0],
        ];
        for ($i = 0; $i < 22; ++$i) {
            [$msg, $cls, $retries] = $emailFailures[$i % \count($emailFailures)];
            $envelopes[] = $this->makeFailedEnvelope(
                new SendOrderConfirmationEmail(
                    orderReference: \sprintf('ORD-%s-%05d', strtoupper(bin2hex(random_bytes(2))), $i),
                    customerEmail: \sprintf('customer.%d@shop.co', $i),
                    totalCents: random_int(2000, 18000),
                ),
                $cls,
                $msg,
                new DateTimeImmutable(\sprintf('-%d minutes', random_int(5, 1440))),
                $retries,
            );
        }

        // 18 meilisearch sync failures.
        $searchFailures = [
            ['Meilisearch 503 — index temporarily locked', RuntimeException::class, 2],
            ['Connection refused on meilisearch:7700', RuntimeException::class, 0],
            ['Document size exceeds 100KB hard limit', DomainException::class, 0],
            ['Invalid API key — rotate and retry', LogicException::class, 1],
        ];
        for ($i = 0; $i < 18; ++$i) {
            [$msg, $cls, $retries] = $searchFailures[$i % \count($searchFailures)];
            $envelopes[] = $this->makeFailedEnvelope(
                new SyncProductToMeilisearch(
                    productId: bin2hex(random_bytes(8)),
                    sku: \sprintf('SKU-%s', strtoupper(bin2hex(random_bytes(4)))),
                    name: \sprintf('Product #%d', 1000 + $i),
                ),
                $cls,
                $msg,
                new DateTimeImmutable(\sprintf('-%d hours', random_int(1, 72))),
                $retries,
            );
        }

        // 10 payment webhook retries.
        $paymentFailures = [
            ['Stripe API timeout after 30s', RuntimeException::class, 3],
            ['Webhook signature mismatch — replay attack?', DomainException::class, 0],
            ['HTTP 502 Bad Gateway from payment-gateway.shopco.io', RuntimeException::class, 1],
            ['DNS resolution failed for payment-gateway.shopco.io', RuntimeException::class, 0],
        ];
        for ($i = 0; $i < 10; ++$i) {
            [$msg, $cls, $retries] = $paymentFailures[$i % \count($paymentFailures)];
            $envelopes[] = $this->makeFailedEnvelope(
                new RetryPaymentWebhook(
                    orderReference: \sprintf('ORD-%s-%05d', strtoupper(bin2hex(random_bytes(2))), $i),
                    transactionId: \sprintf('tx_%s', bin2hex(random_bytes(8))),
                    endpoint: 'https://payment-gateway.shopco.io/webhooks/v2',
                ),
                $cls,
                $msg,
                new DateTimeImmutable(\sprintf('-%d minutes', random_int(2, 600))),
                $retries,
            );
        }

        return $envelopes;
    }

    private function makeFailedEnvelope(
        object $message,
        string $exceptionClass,
        string $exceptionMessage,
        DateTimeImmutable $redeliveredAt,
        int $retryCount,
    ): Envelope {
        return new Envelope(
            $message,
            [
                new SentToFailureTransportStamp('async'),
                new ErrorDetailsStamp(
                    $exceptionClass,
                    0,
                    $exceptionMessage,
                ),
                new RedeliveryStamp($retryCount),
            ],
        );
    }
}

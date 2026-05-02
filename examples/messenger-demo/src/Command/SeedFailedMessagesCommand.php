<?php

declare(strict_types=1);

namespace Polysource\Demo\Messenger\Command;

use DateTimeImmutable;
use DomainException;
use LogicException;
use Polysource\Demo\Messenger\Message\GenerateInvoiceMessage;
use Polysource\Demo\Messenger\Message\PaymentChargeMessage;
use Polysource\Demo\Messenger\Message\SendWelcomeEmailMessage;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Seed the `failed` messenger transport with a mix of realistic
 * envelopes so the dashboard renders something interesting on first
 * page load.
 *
 * Bypasses the message bus + worker on purpose — we don't want the
 * demo to wait 10 retries before a message lands on the failure
 * transport. Stamps are crafted by hand to look like what Symfony's
 * SendFailedMessageToFailureTransportListener would produce.
 */
#[AsCommand(name: 'polysource:demo:seed', description: 'Seed the failed transport with sample envelopes.')]
final class SeedFailedMessagesCommand extends Command
{
    public function __construct(
        private readonly TransportInterface $failedTransport,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $envelopes = [
            self::makeFailedEnvelope(
                new PaymentChargeMessage(orderId: 'ord-2026-04-29-7321', amountCents: 12_990, currency: 'EUR'),
                exceptionClass: RuntimeException::class,
                exceptionMessage: 'Stripe API timeout after 30s',
                redeliveredAt: new DateTimeImmutable('-2 hours'),
                retryCount: 3,
            ),
            self::makeFailedEnvelope(
                new SendWelcomeEmailMessage(userId: 'usr-87431', email: 'alice@example.com'),
                exceptionClass: LogicException::class,
                exceptionMessage: 'SMTP rate limit hit (5/min)',
                redeliveredAt: new DateTimeImmutable('-25 minutes'),
                retryCount: 1,
            ),
            self::makeFailedEnvelope(
                new GenerateInvoiceMessage(invoiceId: 'inv-2026-04-30-001', customerId: 'cust-9912', totalCents: 89_990),
                exceptionClass: RuntimeException::class,
                exceptionMessage: 'PDF rendering OOM (4GB exceeded)',
                redeliveredAt: new DateTimeImmutable('-3 minutes'),
                retryCount: 0,
            ),
            self::makeFailedEnvelope(
                new PaymentChargeMessage(orderId: 'ord-2026-04-30-1108', amountCents: 4_500, currency: 'USD'),
                exceptionClass: DomainException::class,
                exceptionMessage: 'Card declined: insufficient_funds',
                redeliveredAt: new DateTimeImmutable('-90 minutes'),
                retryCount: 2,
            ),
            self::makeFailedEnvelope(
                new SendWelcomeEmailMessage(userId: 'usr-87432', email: 'bob@example.com'),
                exceptionClass: RuntimeException::class,
                exceptionMessage: 'Connection refused on smtp.mailgun.org:587',
                redeliveredAt: new DateTimeImmutable('-10 minutes'),
                retryCount: 1,
            ),
        ];

        foreach ($envelopes as $envelope) {
            $this->failedTransport->send($envelope);
        }

        $io->success(\sprintf('%d envelopes seeded into the failed transport.', \count($envelopes)));
        $io->writeln('Visit <comment>http://localhost:8080/admin/failed-messages</comment> (login: admin / admin).');

        return Command::SUCCESS;
    }

    private static function makeFailedEnvelope(
        object $message,
        string $exceptionClass,
        string $exceptionMessage,
        DateTimeImmutable $redeliveredAt,
        int $retryCount,
    ): Envelope {
        return (new Envelope($message))
            ->with(new SentToFailureTransportStamp('async'))
            ->with(new ErrorDetailsStamp($exceptionClass, 0, $exceptionMessage))
            ->with(new RedeliveryStamp($retryCount, $redeliveredAt))
        ;
    }
}

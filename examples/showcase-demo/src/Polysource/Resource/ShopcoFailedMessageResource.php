<?php

declare(strict_types=1);

namespace App\Polysource\Resource;

use App\Polysource\Field\Field;
use Polysource\Adapter\Messenger\Resource\FailedMessageResource;

/**
 * Showcase override of the upstream FailedMessageResource — adds
 * concrete `configureFields()` so the index + detail pages render
 * actual columns instead of empty rows.
 *
 * The upstream resource intentionally returns `[]` per ADR-011
 * (concrete field types deferred to v0.2). Until v0.2 lands the host
 * wires its own fields. Service override happens in services.yaml so
 * the bundle's tag (`polysource.resource`) stays attached to a single
 * service ID and the route loader still mounts the resource.
 *
 * DataRecord properties exposed by EnvelopeMapper:
 *   - message_class      (FQCN of the failed message)
 *   - failed_at          (DateTimeImmutable, UTC)
 *   - exception_class    (FQCN of the throwable)
 *   - exception_message  (string)
 *   - payload            (json|var_export string)
 *   - payload_format     ('json' | 'print_r')
 */
final class ShopcoFailedMessageResource extends FailedMessageResource
{
    public function configureFields(string $page): iterable
    {
        yield Field::new('id', 'ID')->asId();
        yield Field::new('message_class', 'Message')->asText();
        yield Field::new('failed_at', 'Failed at')->asDateTime();
        yield Field::new('exception_class', 'Exception')->asText();
        yield Field::new('exception_message', 'Reason')->asText();

        if ($page === 'detail') {
            yield Field::new('payload', 'Payload')->asCode();
            yield Field::new('payload_format', 'Format')->asText();
        }
    }
}

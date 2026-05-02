<?php

declare(strict_types=1);

namespace Polysource\Demo\Messenger\Resource;

use Polysource\Adapter\Messenger\Resource\FailedMessageResource;
use Polysource\Demo\Messenger\Field\CodeField;
use Polysource\Demo\Messenger\Field\DateTimeField;
use Polysource\Demo\Messenger\Field\IdField;
use Polysource\Demo\Messenger\Field\TextField;

/**
 * Subclass that adds field configuration to the upstream
 * `FailedMessageResource`.
 *
 * v0.1 of `polysource/core` ships only the abstract `FieldInterface` +
 * `FieldTrait`; concrete field types (TextField, IdField, etc.) live
 * in the host application until ADR-011 closes the question of
 * shipping them in core. This subclass demonstrates the pattern host
 * apps follow today.
 */
final class DemoFailedMessageResource extends FailedMessageResource
{
    public function configureFields(string $page): iterable
    {
        yield IdField::new('message_class', 'Message');
        yield TextField::new('exception_class', 'Exception')->onlyOnIndex();
        yield TextField::new('exception_message', 'Reason');
        yield DateTimeField::new('failed_at', 'Failed at');
        yield CodeField::new('payload', 'Payload')->onlyOnDetail();
    }
}

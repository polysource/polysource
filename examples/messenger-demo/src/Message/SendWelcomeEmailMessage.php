<?php

declare(strict_types=1);

namespace Polysource\Demo\Messenger\Message;

final readonly class SendWelcomeEmailMessage
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {
    }
}

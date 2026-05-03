<?php

declare(strict_types=1);

use Polysource\Demo\EasyAdminBridge\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return static fn (array $context): Kernel => new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

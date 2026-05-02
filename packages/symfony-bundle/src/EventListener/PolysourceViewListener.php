<?php

declare(strict_types=1);

namespace Polysource\Bundle\EventListener;

use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\ResourceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * Renders {@see PolysourceView} return values from controllers.
 *
 * Strategy:
 *   - Twig is the canonical renderer; the bundle requires
 *     `polysource/twig-theme` for the `@Polysource` namespace.
 *   - If a `LoaderError` is raised, we log it and fall back to a
 *     **safe** JSON payload — never including raw record properties,
 *     payloads or exception messages, which could leak PII or
 *     credentials from a Messenger envelope.
 *
 * The JSON fallback is a safety net, NOT a public API: callers should
 * never rely on its shape. Operators should monitor the logger for
 * `LoaderError` warnings — a sustained stream means Twig is
 * misconfigured.
 */
final readonly class PolysourceViewListener implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private ?Environment $twig = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(ViewEvent $event): void
    {
        $view = $event->getControllerResult();
        if (!$view instanceof PolysourceView) {
            return;
        }

        if (null === $this->twig) {
            $this->logger->warning('Polysource view listener: Twig environment not available; falling back to safe JSON.', [
                'template' => $view->template,
            ]);
            $event->setResponse($this->jsonFallback($view));

            return;
        }

        try {
            $body = $this->twig->render($view->template, $view->variables);
            $event->setResponse(new Response($body, $view->statusCode));
        } catch (LoaderError $e) {
            // LoaderError covers two distinct causes:
            //   1) The Polysource Twig namespace (`@Polysource`) was
            //      never registered (composer install --no-dev or the
            //      twig-theme package is missing) — operator action.
            //   2) The specific template path does not exist within an
            //      otherwise correctly configured loader.
            // Both are equally serious in production: a user-facing
            // page just failed to render. We log at warning level and
            // emit a sanitised JSON response so the request still
            // completes.
            $this->logger->warning('Polysource view listener: Twig LoaderError, falling back to safe JSON.', [
                'template' => $view->template,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
            $event->setResponse($this->jsonFallback($view));
        }
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['__invoke', 0],
        ];
    }

    private function jsonFallback(PolysourceView $view): JsonResponse
    {
        return new JsonResponse(self::serialiseSafely($view), $view->statusCode);
    }

    /**
     * Build a *minimal* JSON payload — never includes record properties
     * or payload bodies, which can carry PII from a Messenger envelope
     * or any opaque adapter response.
     *
     * @return array<string, mixed>
     */
    private static function serialiseSafely(PolysourceView $view): array
    {
        $variables = $view->variables;

        $payload = [
            'template' => $view->template,
            'fallback' => 'json',
        ];

        $resource = $variables['resource'] ?? null;
        if ($resource instanceof ResourceInterface) {
            $payload['resource'] = [
                'name' => $resource->getName(),
                'label' => $resource->getLabel(),
            ];
        }

        $page = $variables['page'] ?? null;
        if ($page instanceof DataPage) {
            $payload['page'] = [
                'total' => $page->total,
                'item_count' => \count($page->asArray()),
                'item_ids' => array_map(self::extractRecordId(...), $page->asArray()),
            ];
        }

        $record = $variables['record'] ?? null;
        if ($record instanceof DataRecord) {
            $payload['record'] = ['id' => $record->identifier];
        }

        return $payload;
    }

    private static function extractRecordId(DataRecord $record): int|string
    {
        return $record->identifier;
    }
}

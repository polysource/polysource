<?php

declare(strict_types=1);

namespace Polysource\Bundle\EventListener;

use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\ResourceInterface;
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
 *   - If Twig is available and the template exists, render Twig.
 *   - Otherwise (template not found), fall back to a structured JSON
 *     payload. This fallback exists primarily for v0.1 Phase 2 — the
 *     Twig theme ships in Phase 3.
 */
final readonly class PolysourceViewListener implements EventSubscriberInterface
{
    public function __construct(
        private ?Environment $twig = null,
    ) {
    }

    public function __invoke(ViewEvent $event): void
    {
        $view = $event->getControllerResult();
        if (!$view instanceof PolysourceView) {
            return;
        }

        if (null !== $this->twig) {
            try {
                $body = $this->twig->render($view->template, $view->variables);
                $event->setResponse(new Response($body, $view->statusCode));

                return;
            } catch (LoaderError) {
                // Template missing — fall through to JSON. Phase 3 ships
                // the templates in `polysource/twig-theme`.
            }
        }

        $event->setResponse(new JsonResponse(
            self::serialise($view),
            $view->statusCode,
        ));
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

    /**
     * @return array<string, mixed>
     */
    private static function serialise(PolysourceView $view): array
    {
        $variables = $view->variables;

        $payload = [
            'template' => $view->template,
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
                'items' => array_map(self::serialiseRecord(...), $page->asArray()),
            ];
        }

        $record = $variables['record'] ?? null;
        if ($record instanceof DataRecord) {
            $payload['record'] = self::serialiseRecord($record);
        }

        return $payload;
    }

    /**
     * @return array{id: int|string, properties: array<string, mixed>}
     */
    private static function serialiseRecord(DataRecord $record): array
    {
        return [
            'id' => $record->identifier,
            'properties' => $record->properties,
        ];
    }
}

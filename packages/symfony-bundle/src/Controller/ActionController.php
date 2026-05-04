<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\Routing\PolysourceUrlGenerator;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Exception\ResourceNotFoundException;
use Polysource\Core\Exception\UnsupportedOperationException;
use Polysource\Core\Resource\ResourceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;

/**
 * Executes inline (single-record) and bulk (many-records) actions.
 *
 * Inline action: `POST /{prefix}/{resourceName}/{id}/{action}`.
 * Bulk action:   `POST /{prefix}/{resourceName}/batch/{action}` with form
 * data `ids[]=1&ids[]=2`.
 *
 * Both endpoints require a CSRF token in the `_token` field. The token id
 * is `polysource_action`. Bulk requests with more than `max_bulk_ids`
 * identifiers are rejected with `400 Bad Request`.
 *
 * On success or graceful failure (`ActionResult::failure()`), a flash
 * message is pushed and the response redirects back to the resource
 * index. If an action throws unexpectedly, the exception is logged,
 * the flash bag carries a sanitised "Unexpected error" message, and
 * the request still redirects (rather than producing a naked 500).
 */
final class ActionController
{
    public const CSRF_TOKEN_ID = 'polysource_action';

    private LoggerInterface $logger;

    public function __construct(
        private readonly PolysourceUrlGenerator $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ControllerSupport $support,
        private readonly int $maxBulkIds = 500,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(AdminContext $context): Response
    {
        $this->support->assertResourceAccess($context->resource);
        $this->assertCsrf($context);

        if (null === $context->recordId) {
            throw new ResourceNotFoundException(\sprintf('Action route for resource "%s" requires an "id" parameter.', $context->resource->getName()));
        }

        $action = self::findAction($context->resource, $context->action);
        if (!$action instanceof InlineActionInterface) {
            throw new UnsupportedOperationException(\sprintf('Action "%s" on resource "%s" is not an inline action.', $context->action, $context->resource->getName()));
        }

        $this->support->assertActionAccess($action);

        $record = $context->resource->getDataSource()->find($context->recordId);
        if (null === $record) {
            throw new ResourceNotFoundException(\sprintf('Record "%s" not found in resource "%s".', $context->recordId, $context->resource->getName()));
        }

        $result = $this->safelyRun(static fn (): ActionResult => $action->execute($record), $action);
        self::pushFlash($context->request, $result);

        return new RedirectResponse($this->urlGenerator->index($context->resource->getName()));
    }

    public function bulk(AdminContext $context): Response
    {
        $this->support->assertResourceAccess($context->resource);
        $this->assertCsrf($context);

        $rawIds = $context->request->request->all('ids');
        if (\count($rawIds) > $this->maxBulkIds) {
            throw new BadRequestHttpException(\sprintf('Bulk action accepts at most %d ids per request, got %d.', $this->maxBulkIds, \count($rawIds)));
        }

        $actionName = $context->request->attributes->get('action');
        if (!\is_string($actionName) || '' === $actionName) {
            throw new ResourceNotFoundException(\sprintf('Bulk action route for resource "%s" requires an "action" parameter.', $context->resource->getName()));
        }

        $action = self::findAction($context->resource, $actionName);
        if (!$action instanceof BulkActionInterface) {
            throw new UnsupportedOperationException(\sprintf('Action "%s" on resource "%s" is not a bulk action.', $actionName, $context->resource->getName()));
        }

        $this->support->assertActionAccess($action);

        $stringIds = [];
        foreach ($rawIds as $id) {
            if (\is_scalar($id)) {
                $stringIds[] = (string) $id;
            }
        }
        $stringIds = array_values(array_unique($stringIds));

        $records = [];
        foreach ($stringIds as $id) {
            $record = $context->resource->getDataSource()->find($id);
            if (null !== $record) {
                $records[] = $record;
            }
        }

        $result = $this->safelyRun(static fn (): ActionResult => $action->executeBatch($records), $action);
        self::pushFlash($context->request, $result);

        return new RedirectResponse($this->urlGenerator->index($context->resource->getName()));
    }

    /**
     * Run an action callable, converting an unexpected `Throwable` to a
     * graceful `ActionResult::failure()`. The exception is logged so
     * operators can investigate without the user-facing flash leaking
     * stack-trace text.
     *
     * @param callable():ActionResult $callable
     */
    private function safelyRun(callable $callable, ActionInterface $action): ActionResult
    {
        try {
            return $callable();
        } catch (Throwable $e) {
            $this->logger->error('Polysource: action threw unexpectedly.', [
                'action_name' => $action->getName(),
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return ActionResult::failure(\sprintf('Action "%s" failed unexpectedly. Operators have been notified.', $action->getName()));
        }
    }

    private static function pushFlash(Request $request, ActionResult $result): void
    {
        if (null === $result->message) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if (!$session instanceof Session) {
            return;
        }

        $session->getFlashBag()->add(
            $result->success ? 'success' : 'error',
            $result->message,
        );
    }

    private function assertCsrf(AdminContext $context): void
    {
        $token = $context->request->request->get('_token');
        if (!\is_string($token) || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new AccessDeniedHttpException('Invalid or missing CSRF token.');
        }
    }

    private static function findAction(ResourceInterface $resource, string $name): ?ActionInterface
    {
        foreach ($resource->configureActions() as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }
}

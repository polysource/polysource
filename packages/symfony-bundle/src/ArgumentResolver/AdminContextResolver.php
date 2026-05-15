<?php

declare(strict_types=1);

namespace Polysource\Bundle\ArgumentResolver;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\Context\AdminContextProvider;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;
use Polysource\Core\Query\Pagination;
use Polysource\Core\Query\SortDirection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Builds an {@see AdminContext} from the current request and yields it
 * for any controller argument typed as `AdminContext`.
 *
 * Cf. ADR-003 — physical routes carry `resourceName`, `id`, `action` as
 * route attributes. We derive the rest (DataQuery, user, locale) from the
 * request.
 */
final class AdminContextResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly AdminContextProvider $provider,
        private readonly ?Security $security = null,
        private readonly int $maxPageSize = 200,
    ) {
    }

    /**
     * @return iterable<AdminContext>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (AdminContext::class !== $argument->getType()) {
            return [];
        }

        $resourceName = $request->attributes->get('resourceName');
        if (!\is_string($resourceName) || '' === $resourceName) {
            return [];
        }

        $resource = $this->resources->get($resourceName);

        $action = $this->resolveAction($request);
        $recordId = $this->resolveRecordId($request);
        $query = $this->buildQuery($resourceName, $request);
        $user = null !== $this->security ? $this->security->getUser() : null;

        $context = new AdminContext(
            request: $request,
            resource: $resource,
            action: $action,
            recordId: $recordId,
            locale: $request->getLocale(),
            user: $user,
            query: $query,
        );

        // Note: kernel.reset clears the provider between master requests
        // but not between a master and its sub-requests. Twig embed panels
        // that render inside another Polysource page will overwrite the
        // outer context here. Acceptable for v0.1 (no embed panels yet).
        $this->provider->setContext($context);

        yield $context;
    }

    private function resolveAction(Request $request): string
    {
        $action = $request->attributes->get('action');
        if (\is_string($action) && '' !== $action) {
            return $action;
        }

        $defaulted = $request->attributes->get('_polysource_action');
        if (\is_string($defaulted) && '' !== $defaulted) {
            return $defaulted;
        }

        return 'index';
    }

    private function resolveRecordId(Request $request): ?string
    {
        $id = $request->attributes->get('id');
        if (null === $id) {
            return null;
        }
        if (\is_string($id) || \is_int($id)) {
            return (string) $id;
        }

        return null;
    }

    private function buildQuery(string $resourceName, Request $request): DataQuery
    {
        $query = new DataQuery($resourceName);

        $searchText = $request->query->get('q');
        if (\is_string($searchText) && '' !== $searchText) {
            $query = $query->withSearchText($searchText);
        }

        $filters = $request->query->all('filter');
        foreach ($filters as $name => $value) {
            if (!\is_string($name)) {
                continue;
            }
            $criterion = self::decodeCriterion($name, $value);
            if (null === $criterion) {
                continue;
            }
            $query = $query->withFilter($name, $criterion);
        }

        $sort = $request->query->all('sort');
        foreach ($sort as $property => $direction) {
            if (!\is_string($property) || !\is_string($direction)) {
                continue;
            }
            $dir = SortDirection::tryFrom(strtolower($direction));
            if (null === $dir) {
                continue;
            }
            $query = $query->withSort($property, $dir);
        }

        // Cap pageSize to prevent unbounded paginations from cascading to
        // adapters (Meilisearch, S3, HTTP). The ceiling is configurable
        // via `polysource.max_page_size`.
        $rawPageSize = $request->query->getInt('pageSize', 20);
        $pageSize = max(1, min($rawPageSize, $this->maxPageSize));
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * $pageSize;
        $cursor = $request->query->get('cursor');

        $query = $query->withPagination(new Pagination(
            offset: $offset,
            limit: $pageSize,
            cursor: \is_string($cursor) && '' !== $cursor ? $cursor : null,
        ));

        return $query;
    }

    /**
     * Decode the inbound URL-encoded criterion. Two shapes are accepted:
     *   `?filter[name]=value`                                        → eq, scalar
     *   `?filter[name][op]=like&filter[name][value]=foo`             → operator, scalar
     *   `?filter[name][op]=in&filter[name][values][]=a&...[]=b`      → operator, list
     *   `?filter[name][op]=between&filter[name][min]=...&[max]=...`  → between, [min, max]
     *
     * Empty / whitespace-only values produce no criterion (so an
     * applied-then-cleared filter doesn't leak into adapter queries).
     */
    private static function decodeCriterion(string $name, mixed $value): ?FilterCriterion
    {
        if (\is_scalar($value)) {
            $scalar = (string) $value;
            if ('' === trim($scalar)) {
                return null;
            }

            return new FilterCriterion($name, FilterOperator::Eq, $scalar);
        }

        if (!\is_array($value)) {
            return null;
        }

        // Unknown operator strings silently fall back to Eq — preserves
        // URL backward compat for bookmarks with stale `op=...` params.
        $opString = \is_string($value['op'] ?? null) ? $value['op'] : 'eq';
        $operator = FilterOperator::tryFrom($opString) ?? FilterOperator::Eq;

        if (isset($value['values']) && \is_array($value['values'])) {
            $list = array_values(array_filter(
                array_map(static fn ($v): string => \is_scalar($v) ? trim((string) $v) : '', $value['values']),
                static fn (string $v): bool => '' !== $v,
            ));
            if ([] === $list) {
                return null;
            }

            return new FilterCriterion($name, $operator, $list);
        }

        if (isset($value['min']) || isset($value['max'])) {
            $min = \is_scalar($value['min'] ?? null) ? (string) $value['min'] : '';
            $max = \is_scalar($value['max'] ?? null) ? (string) $value['max'] : '';
            if ('' === $min && '' === $max) {
                return null;
            }

            return new FilterCriterion($name, FilterOperator::Between, [$min, $max]);
        }

        if (isset($value['value']) && \is_scalar($value['value'])) {
            $scalar = trim((string) $value['value']);
            if ('' === $scalar) {
                return null;
            }

            return new FilterCriterion($name, $operator, $scalar);
        }

        return null;
    }
}

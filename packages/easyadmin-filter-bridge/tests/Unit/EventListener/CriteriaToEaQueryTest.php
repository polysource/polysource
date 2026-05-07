<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\EventListener\SavedViewApplySubscriber;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Storage\InMemorySavedViewStorage;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Pins the URL shape produced by
 * {@see SavedViewApplySubscriber} when replaying a saved view —
 * the half of the roundtrip that emits the URL the browser
 * navigates to, after the user clicks a saved view in the
 * dropdown.
 *
 * Two shape rules:
 *
 * 1. Filters whose FormType is {@see BooleanFilterType} (a Symfony
 *    ChoiceType — no comparison/value envelope) emit a bare scalar:
 *    `filters[X]=<value>`.
 *
 * 2. Every other FormType emits the comparison/value envelope:
 *    `filters[X][comparison]=<op>&filters[X][value]=<v>`.
 *
 * Mismatching the rule was the headline regression of 2026-05-07:
 * the chips bar rendered the right declared filters but the table
 * showed every row because Symfony's ChoiceType form binding can't
 * unpack the envelope shape into its single-value model.
 */
final class CriteriaToEaQueryTest extends TestCase
{
    private SavedViewApplySubscriber $subscriber;
    private ReflectionMethod $criteriaToEaQuery;

    protected function setUp(): void
    {
        // SavedViewService is final — build a real instance with
        // in-memory storage. The subscriber under test never calls
        // it from criteriaToEaQuery() so the wiring is just to
        // satisfy the constructor.
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);
        $service = new SavedViewService(
            storage: new InMemorySavedViewStorage(),
            authChecker: $authChecker,
            tokenStorage: new TokenStorage(),
        );
        $this->subscriber = new SavedViewApplySubscriber($service, new RequestStack());

        $this->criteriaToEaQuery = new ReflectionMethod($this->subscriber, 'criteriaToEaQuery');
    }

    #[Test]
    public function textFilterEmitsEnvelopeShape(): void
    {
        $config = $this->configWith(['name' => TextFilter::new('name')]);
        $collection = new FilterCollection('products', [
            new FilterCriterion('name', 'like', ['foo']),
        ]);

        $result = $this->criteriaToEaQuery->invoke($this->subscriber, $collection, $config);

        self::assertSame(
            ['filters' => ['name' => ['comparison' => 'like', 'value' => 'foo']]],
            $result,
            'TextFilter is ComparisonFilterType-derived → envelope shape',
        );
    }

    #[Test]
    public function numericFilterEmitsEnvelopeShape(): void
    {
        $config = $this->configWith(['stock' => NumericFilter::new('stock')]);
        $collection = new FilterCollection('products', [
            new FilterCriterion('stock', 'gte', ['100']),
        ]);

        $result = $this->criteriaToEaQuery->invoke($this->subscriber, $collection, $config);

        self::assertSame(
            ['filters' => ['stock' => ['comparison' => '>=', 'value' => '100']]],
            $result,
        );
    }

    #[Test]
    public function booleanFilterEmitsBareScalarShape(): void
    {
        $config = $this->configWith(['isSent' => BooleanFilter::new('isSent')]);
        $collection = new FilterCollection('products', [
            new FilterCriterion('isSent', 'eq', ['1']),
        ]);

        $result = $this->criteriaToEaQuery->invoke($this->subscriber, $collection, $config);

        self::assertSame(
            ['filters' => ['isSent' => '1']],
            $result,
            'BooleanFilter is ChoiceType (no envelope) → bare scalar shape',
        );
    }

    #[Test]
    public function choiceMultiEmitsListWithEqualsComparison(): void
    {
        $config = $this->configWith(['status' => ChoiceFilter::new('status')->setChoices(['Active' => 'active', 'Draft' => 'draft'])]);
        $collection = new FilterCollection('products', [
            new FilterCriterion('status', 'in', ['active', 'draft']),
        ]);

        $result = $this->criteriaToEaQuery->invoke($this->subscriber, $collection, $config);

        self::assertSame(
            ['filters' => ['status' => ['comparison' => '=', 'value' => ['active', 'draft']]]],
            $result,
            'in operator must serialise back to comparison: "=" — what EA expects for multi-select',
        );
    }

    #[Test]
    public function betweenEmitsMinMaxEnvelope(): void
    {
        $config = $this->configWith(['createdAt' => TextFilter::new('createdAt')]);
        $collection = new FilterCollection('products', [
            new FilterCriterion('createdAt', 'between', ['2026-01-01', '2026-12-31']),
        ]);

        $result = $this->criteriaToEaQuery->invoke($this->subscriber, $collection, $config);

        self::assertSame(
            ['filters' => ['createdAt' => ['comparison' => 'between', 'value' => ['min' => '2026-01-01', 'max' => '2026-12-31']]]],
            $result,
        );
    }

    #[Test]
    public function multipleCriteriaInOneCollection(): void
    {
        $config = $this->configWith([
            'name' => TextFilter::new('name'),
            'isSent' => BooleanFilter::new('isSent'),
            'status' => ChoiceFilter::new('status'),
        ]);
        $collection = new FilterCollection('products', [
            new FilterCriterion('name', 'like', ['foo']),
            new FilterCriterion('isSent', 'eq', ['0']),
            new FilterCriterion('status', 'in', ['active']),
        ]);

        $result = $this->criteriaToEaQuery->invoke($this->subscriber, $collection, $config);

        self::assertSame([
            'filters' => [
                'name' => ['comparison' => 'like', 'value' => 'foo'],
                'isSent' => '0',
                'status' => ['comparison' => '=', 'value' => ['active']],
            ],
        ], $result);
    }

    /**
     * @param array<string, \EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface> $filters
     */
    private function configWith(array $filters): FilterConfigDto
    {
        $config = new FilterConfigDto();
        foreach ($filters as $filter) {
            $config->addFilter($filter);
        }

        return $config;
    }
}

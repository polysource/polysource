<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Form\Type;

use Polysource\EasyAdminFilterBridge\Form\Type\BetweenDateFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\FullTextSearchFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\InFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\NotNullFilterType;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * Regression: EA 5.5 typed `FilterDataDto::$comparison` as a
 * NON-NULLABLE string. Any custom filter form type whose submitted
 * `comparison` resolves to null fatals with "Cannot assign null to
 * property FilterDataDto::$comparison" at the FIRST submission of the
 * filters modal — EA submits every configured filter slice at once,
 * so an unfilled FullTextSearch/NotNull filter was enough to 500 the
 * whole index page (found in production-host integration, 2026-08).
 *
 * Root cause: `empty_data: ''` NEVER applies — Symfony Forms treats
 * the empty string itself as "empty", so the field resolves to null.
 * The sentinel must be a non-empty string, as BetweenDate ('between')
 * and In ('IN') always did.
 *
 * These tests submit the EXACT shapes the EA modal (and hostile /
 * hand-edited URLs) produce, through a real form factory — the bug is
 * a submission-semantics bug, invisible to stub-driven tests.
 */
final class ComparisonSentinelSubmissionTest extends TypeTestCase
{
    /**
     * @dataProvider comparisonShapeProvider
     *
     * @param class-string                     $typeClass
     * @param array<string, mixed>             $typeOptions
     * @param array<string, string|array<int, string>> $submission
     */
    public function testSubmittedComparisonIsNeverNull(
        string $typeClass,
        array $typeOptions,
        array $submission,
        string $expectedComparison,
    ): void {
        $form = $this->factory->create($typeClass, null, $typeOptions);

        $form->submit($submission);

        $data = $form->getData();
        self::assertIsArray($data);
        self::assertArrayHasKey('comparison', $data);
        self::assertNotNull(
            $data['comparison'],
            'null comparison fatals on EA >= 5.5 (non-nullable FilterDataDto::$comparison)',
        );
        self::assertSame($expectedComparison, $data['comparison']);
    }

    /**
     * @return iterable<string, array{class-string, array<string, mixed>, array<string, mixed>, string}>
     */
    public static function comparisonShapeProvider(): iterable
    {
        // The EA modal always submits every configured slice, filled or not.
        yield 'fulltext, modal shape, filled' => [
            FullTextSearchFilterType::class, [], ['comparison' => '', 'value' => 'foo'], '=',
        ];
        yield 'fulltext, modal shape, unfilled' => [
            FullTextSearchFilterType::class, [], ['comparison' => '', 'value' => ''], '=',
        ];
        yield 'fulltext, comparison key absent (hand-edited URL)' => [
            FullTextSearchFilterType::class, [], ['value' => 'foo'], '=',
        ];
        yield 'not-null, modal shape, filled' => [
            NotNullFilterType::class, [], ['comparison' => '', 'value' => NotNullFilterType::VALUE_NOT_NULL], '=',
        ];
        yield 'not-null, modal shape, unfilled (Any)' => [
            NotNullFilterType::class, [], ['comparison' => '', 'value' => NotNullFilterType::VALUE_ANY], '=',
        ];
        yield 'between-date keeps its sentinel on hostile empty comparison' => [
            BetweenDateFilterType::class, [], ['comparison' => '', 'value' => '2026-01-01', 'value2' => ''], 'between',
        ];
        yield 'in keeps its sentinel on hostile empty comparison' => [
            InFilterType::class, ['choices' => ['A' => 'a']], ['comparison' => '', 'value' => ['a']], 'IN',
        ];
    }
}

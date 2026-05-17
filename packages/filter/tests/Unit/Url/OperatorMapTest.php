<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\Url;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Url\OperatorMap;

#[CoversClass(OperatorMap::class)]
final class OperatorMapTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideEaToCanonical(): iterable
    {
        // Mathematical-symbol forms.
        yield '= → eq' => ['=', OperatorMap::EQ];
        yield '!= → neq' => ['!=', OperatorMap::NEQ];
        yield '<> → neq' => ['<>', OperatorMap::NEQ];
        yield '> → gt' => ['>', OperatorMap::GT];
        yield '>= → gte' => ['>=', OperatorMap::GTE];
        yield '< → lt' => ['<', OperatorMap::LT];
        yield '<= → lte' => ['<=', OperatorMap::LTE];

        // Keyword forms (lower / upper / mixed case all accepted).
        yield 'like → like' => ['like', OperatorMap::LIKE];
        yield 'LIKE → like (case insensitive)' => ['LIKE', OperatorMap::LIKE];
        yield 'not like → not like' => ['not like', OperatorMap::NOT_LIKE];
        yield 'in → in' => ['in', OperatorMap::IN];
        yield 'between → between' => ['between', OperatorMap::BETWEEN];

        // Null-check forms with snake_case aliases (Polysource's
        // NotNullFilter submits both shapes depending on the source).
        yield 'is null → is null' => ['is null', OperatorMap::IS_NULL];
        yield 'is_null → is null (snake alias)' => ['is_null', OperatorMap::IS_NULL];
        yield 'null → is null (short alias)' => ['null', OperatorMap::IS_NULL];
        yield 'IS NOT NULL → is not null (upper)' => ['IS NOT NULL', OperatorMap::IS_NOT_NULL];
    }

    #[Test]
    #[DataProvider('provideEaToCanonical')]
    public function fromEaTranslatesKnownOperators(string $easyAdminOperator, string $expectedCanonical): void
    {
        self::assertSame($expectedCanonical, OperatorMap::fromEa($easyAdminOperator));
    }

    #[Test]
    public function fromEaFallsBackOnUnknownOperator(): void
    {
        // v0.9.0 hardening: unknown operators no longer pass through
        // verbatim — they collapse to the supplied default. Defense
        // in depth against a hostile client persisting criteria with
        // arbitrary operator text downstream consumers had to defensively
        // reject. Caught by the architectural audit.
        self::assertSame('eq', OperatorMap::fromEa('UNION', 'eq'));
        self::assertSame('between', OperatorMap::fromEa('arbitrary', 'between'));
    }

    #[Test]
    public function fromEaFallsBackOnEmptyString(): void
    {
        self::assertSame('eq', OperatorMap::fromEa(''));
        self::assertSame('gte', OperatorMap::fromEa('   ', 'gte'));
    }

    #[Test]
    public function toEaTranslatesCanonicalOperators(): void
    {
        self::assertSame('=', OperatorMap::toEa(OperatorMap::EQ));
        self::assertSame('!=', OperatorMap::toEa(OperatorMap::NEQ));
        self::assertSame('like', OperatorMap::toEa(OperatorMap::LIKE));
        self::assertSame('between', OperatorMap::toEa(OperatorMap::BETWEEN));
        self::assertSame('IS NULL', OperatorMap::toEa(OperatorMap::IS_NULL));
    }

    #[Test]
    public function toEaFallsBackOnUnknownCanonical(): void
    {
        self::assertSame('=', OperatorMap::toEa('arbitrary'));
    }

    /**
     * The map is the single source of truth for the operator
     * vocabulary — every canonical operator must round-trip via
     * fromEa(toEa(X)) === X. If a regression breaks the inverse,
     * SavedView state on disk (criteria persisted yesterday) can't
     * be replayed today.
     *
     * @return iterable<string, array{string}>
     */
    public static function provideCanonicalOperators(): iterable
    {
        yield 'eq' => [OperatorMap::EQ];
        yield 'neq' => [OperatorMap::NEQ];
        yield 'gt' => [OperatorMap::GT];
        yield 'gte' => [OperatorMap::GTE];
        yield 'lt' => [OperatorMap::LT];
        yield 'lte' => [OperatorMap::LTE];
        yield 'like' => [OperatorMap::LIKE];
        yield 'not like' => [OperatorMap::NOT_LIKE];
        yield 'between' => [OperatorMap::BETWEEN];
        yield 'is null' => [OperatorMap::IS_NULL];
        yield 'is not null' => [OperatorMap::IS_NOT_NULL];
    }

    #[Test]
    #[DataProvider('provideCanonicalOperators')]
    public function canonicalOperatorsRoundTrip(string $canonical): void
    {
        self::assertSame(
            $canonical,
            OperatorMap::fromEa(OperatorMap::toEa($canonical)),
            \sprintf('Canonical operator "%s" failed to round-trip via fromEa(toEa(X))', $canonical),
        );
    }

    #[Test]
    public function isCanonicalRecognisesCanonicalNames(): void
    {
        self::assertTrue(OperatorMap::isCanonical(OperatorMap::EQ));
        self::assertTrue(OperatorMap::isCanonical(OperatorMap::BETWEEN));
        // Case-insensitive — be robust to caller mistakes.
        self::assertTrue(OperatorMap::isCanonical('EQ'));
    }

    #[Test]
    public function isCanonicalRejectsEaUrlForms(): void
    {
        // EA URL forms (`=`, `!=`) are NOT canonical names — they're
        // input that fromEa() translates INTO canonical names. The
        // distinction matters because storage layers (SavedView
        // FilterCollection) only accept canonical operators.
        self::assertFalse(OperatorMap::isCanonical('='));
        self::assertFalse(OperatorMap::isCanonical('!='));
        self::assertFalse(OperatorMap::isCanonical(''));
    }
}

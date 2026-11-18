<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Condition\StoredCondition;

/**
 * A condition in the stored condition format. See "Visibility conditions" in docs/spec/content-model.md
 */
final class StoredConditionTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_a_condition_in_the_stored_condition_format(): void
    {
        $condition = StoredCondition::fromValue(['kind' => 'product_in_taxon', 'negate' => true, 'params' => ['taxons' => ['SHOES']]]);

        self::assertNotNull($condition);
        self::assertSame('product_in_taxon', $condition->kind);
        self::assertTrue($condition->negate);
        self::assertSame(['taxons' => ['SHOES']], $condition->params);
    }

    /**
     * As CanonicalJson::decode() reads an empty JSON object
     *
     * @test
     */
    public function it_reads_an_empty_object_as_empty_parameters(): void
    {
        self::assertSame([], StoredCondition::fromValue(['kind' => 'signed_in', 'negate' => false, 'params' => new \stdClass()])?->params);
        self::assertSame(['locales' => ['en_US']], StoredCondition::fromValue(['kind' => 'locale_is', 'negate' => false, 'params' => (object) ['locales' => ['en_US']]])?->params);
    }

    /**
     * @test
     */
    public function it_reads_nothing_from_a_value_of_another_shape(): void
    {
        foreach ([
            'not a condition',
            ['negate' => false, 'params' => []],
            ['kind' => 'Not A Code', 'negate' => false, 'params' => []],
            ['kind' => 'signed_in', 'negate' => 'no', 'params' => []],
            ['kind' => 'signed_in', 'negate' => false],
            ['kind' => 'signed_in', 'negate' => false, 'params' => ['a list']],
        ] as $value) {
            self::assertNull(StoredCondition::fromValue($value), json_encode($value, \JSON_THROW_ON_ERROR));
        }
    }

    /**
     * So a page scope is stored as it was given
     *
     * @test
     */
    public function it_gives_itself_back_in_the_stored_condition_format(): void
    {
        $value = ['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES'], 'includeChildren' => true]];

        self::assertSame($value, StoredCondition::fromValue($value)?->toArray());
        self::assertSame(['kind' => 'signed_in', 'negate' => true, 'params' => []], StoredCondition::fromValue(['kind' => 'signed_in', 'negate' => true, 'params' => []])?->toArray());
    }
}

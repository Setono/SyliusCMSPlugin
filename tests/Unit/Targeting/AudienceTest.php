<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Targeting;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupValue;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;

/**
 * See "An entry's audience" in docs/spec/targeting.md
 */
final class AudienceTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     *
     * @dataProvider shoppers
     */
    public function everyone_shows_to_every_shopper(string $shopper): void
    {
        self::assertTrue(Audience::everyone()->allows($this->customerGroupValueOf($shopper)));
    }

    /**
     * @test
     *
     * @dataProvider shoppers
     */
    public function only_these_shows_to_the_shoppers_whose_value_is_in_the_list(string $shopper, bool $inList): void
    {
        $audience = Audience::only('wholesale', '@guest');

        self::assertSame($inList, $audience->allows($this->customerGroupValueOf($shopper)));
    }

    /**
     * @test
     *
     * @dataProvider shoppers
     */
    public function everyone_except_these_shows_to_the_shoppers_whose_value_is_not_in_the_list(string $shopper, bool $inList): void
    {
        $audience = Audience::except('wholesale', '@guest');

        self::assertSame(!$inList, $audience->allows($this->customerGroupValueOf($shopper)));
    }

    /**
     * Each shopper, and whether their value is in the list "wholesale, @guest"
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function shoppers(): iterable
    {
        yield 'a customer in the group' => ['wholesale', true];
        yield 'a customer in another group' => ['retail', false];
        yield 'a guest' => ['guest', true];
        yield 'a customer without a group' => ['no group', false];
    }

    /**
     * @test
     */
    public function it_makes_signed_in_without_a_group_a_value_of_its_own(): void
    {
        $audience = Audience::only('@no_group');

        self::assertTrue($audience->allows($this->customerGroupValueOf('no group')));
        self::assertFalse($audience->allows($this->customerGroupValueOf('guest')));
        self::assertFalse($audience->allows($this->customerGroupValueOf('wholesale')));
    }

    /**
     * A deleted customer group leaves a code no customer has. See "Deleted customer groups" in docs/spec/targeting.md
     *
     * @test
     */
    public function it_matches_nobody_with_a_deleted_customer_groups_code(): void
    {
        foreach (['wholesale', 'retail', 'guest', 'no group'] as $shopper) {
            $value = $this->customerGroupValueOf($shopper);

            self::assertFalse(Audience::only('deleted_group')->allows($value), sprintf('"Only these" lets %s in through a deleted group', $shopper));
            self::assertTrue(Audience::except('deleted_group')->allows($value), sprintf('"Everyone except these" keeps %s out through a deleted group', $shopper));
        }
    }

    /**
     * @test
     */
    public function it_has_a_mode_and_its_groups_in_a_stable_order_without_duplicates(): void
    {
        $audience = new Audience(AudienceMode::Only, ['wholesale', '@guest', 'retail', 'wholesale']);

        self::assertSame(AudienceMode::Only, $audience->mode);
        self::assertSame(['@guest', 'retail', 'wholesale'], $audience->groups);
        self::assertSame([], Audience::everyone()->groups);
        self::assertSame(AudienceMode::Except, Audience::except('retail')->mode);
        self::assertSame(['retail'], Audience::except('retail')->groups);
        self::assertSame(['everyone', 'only', 'except'], array_map(static fn (AudienceMode $mode): string => $mode->value, AudienceMode::cases()));
    }

    /**
     * @test
     */
    public function it_is_equal_to_an_audience_with_the_same_mode_and_groups(): void
    {
        self::assertTrue(Audience::only('b', 'a')->equals(Audience::only('a', 'b')));
        self::assertTrue(Audience::everyone()->equals(new Audience(AudienceMode::Everyone)));
        self::assertFalse(Audience::only('a')->equals(Audience::except('a')));
        self::assertFalse(Audience::only('a')->equals(Audience::only('a', 'b')));
        self::assertFalse(Audience::only('a')->equals(Audience::only('b')));
    }

    /**
     * @test
     */
    public function it_has_no_groups_for_everyone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An audience of everyone has no groups');

        new Audience(AudienceMode::Everyone, ['wholesale']);
    }

    /**
     * @test
     *
     * @dataProvider invalidGroups
     */
    public function its_groups_are_customer_group_codes_and_the_reserved_values(string $group): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('"%s" is neither a customer group code, "@guest" nor "@no_group"', $group));

        Audience::only('wholesale', $group);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidGroups(): iterable
    {
        yield 'empty' => [''];
        yield 'another reserved-looking value' => ['@admin'];
        yield 'a code with an @ inside' => ['whole@sale'];
    }

    /**
     * @test
     */
    public function it_gives_the_groups_code_guest_or_no_group_as_the_customer_group_value(): void
    {
        self::assertSame('@guest', CustomerGroupValue::of(null));
        self::assertSame('@no_group', $this->customerGroupValueOf('no group'));
        self::assertSame('wholesale', $this->customerGroupValueOf('wholesale'));
        self::assertTrue(CustomerGroupValue::isReserved('@guest'));
        self::assertTrue(CustomerGroupValue::isReserved('@no_group'));
        self::assertFalse(CustomerGroupValue::isReserved('wholesale'));
    }

    /**
     * What an audience and Shown to accept: a customer group code, which can't contain "@", or a reserved value
     *
     * @test
     */
    public function a_customer_group_value_is_a_code_guest_or_no_group(): void
    {
        self::assertTrue(CustomerGroupValue::isValid('wholesale'));
        self::assertTrue(CustomerGroupValue::isValid('@guest'));
        self::assertTrue(CustomerGroupValue::isValid('@no_group'));

        foreach (self::invalidGroups() as [$value]) {
            self::assertFalse(CustomerGroupValue::isValid($value));
        }
    }

    private function customerGroupValueOf(string $shopper): string
    {
        if ('guest' === $shopper) {
            return CustomerGroupValue::of(null);
        }

        $customer = $this->prophesize(CustomerInterface::class);

        if ('no group' === $shopper) {
            $customer->getGroup()->willReturn(null);
        } else {
            $group = $this->prophesize(CustomerGroupInterface::class);
            $group->getCode()->willReturn($shopper);
            $customer->getGroup()->willReturn($group->reveal());
        }

        return CustomerGroupValue::of($customer->reveal());
    }
}

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\CustomerGroupKind;
use Setono\SyliusCMSPlugin\Condition\Kind\CustomerGroupParams;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;

final class CustomerGroupKindTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_matches_when_the_shoppers_customer_group_value_is_one_of_the_groups(): void
    {
        $params = new CustomerGroupParams();
        $params->groups = ['wholesale', '@guest'];

        self::assertTrue((new CustomerGroupKind())->matches($params, $this->context('wholesale')));
        self::assertTrue((new CustomerGroupKind())->matches($params, $this->context('@guest')), 'A guest');
        self::assertFalse((new CustomerGroupKind())->matches($params, $this->context('@no_group')), 'Signed in without a group');
        self::assertFalse((new CustomerGroupKind())->matches($params, $this->context('retail')));
    }

    /**
     * @test
     */
    public function it_matches_customers_signed_in_without_a_group_by_no_group(): void
    {
        $params = new CustomerGroupParams();
        $params->groups = ['@no_group'];

        self::assertTrue((new CustomerGroupKind())->matches($params, $this->context('@no_group')));
        self::assertFalse((new CustomerGroupKind())->matches($params, $this->context('@guest')));
        self::assertFalse((new CustomerGroupKind())->matches(new CustomerGroupParams(), $this->context('@no_group')));
    }

    /**
     * @test
     */
    public function it_takes_only_its_own_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The customer group condition takes %s, got %s', CustomerGroupParams::class, NoParams::class));

        (new CustomerGroupKind())->matches(new NoParams(), $this->context('@guest'));
    }

    private function context(string $customerGroup): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, $customerGroup, new \DateTimeImmutable());
    }
}

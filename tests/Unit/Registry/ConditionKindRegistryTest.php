<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Kind\DateWindowKind;
use Setono\SyliusCMSPlugin\Condition\Kind\DateWindowParams;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Field\DateTime;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\UnknownConditionKindException;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\LocaleIsParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\SignedIn;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ConditionKindRegistryTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_a_condition_kinds_attribute_and_the_fields_of_its_parameters_class(): void
    {
        $kind = new DateWindowKind();
        $metadata = $this->registry(['date_window' => $kind])->get('date_window');

        self::assertSame('date_window', $metadata->code);
        self::assertSame('setono_sylius_cms.condition_kind.date_window', $metadata->label);
        self::assertSame([], $metadata->needs);
        self::assertSame([Reads::Time], $metadata->reads);
        self::assertSame(DateWindowParams::class, $metadata->params);
        self::assertSame(['from', 'until'], array_keys($metadata->fields));
        self::assertInstanceOf(DateTime::class, $metadata->fields['from']->field);
        self::assertSame($kind, $metadata->kind);
        // A date window is matched inside or outside it
        self::assertSame('setono_sylius_cms.builder.is_inside', $metadata->matches);
        self::assertSame('setono_sylius_cms.builder.is_outside', $metadata->matchesNot);
    }

    /**
     * @test
     */
    public function it_gives_a_kind_without_parameters_no_fields_and_lets_it_not_say_what_it_reads(): void
    {
        $metadata = $this->registry(['signed_in' => new SignedIn()])->get('signed_in');

        self::assertNull($metadata->params);
        self::assertSame([], $metadata->fields);
        self::assertNull($metadata->reads);
    }

    /**
     * @test
     */
    public function it_reads_each_kind_once(): void
    {
        $registry = $this->registry(['signed_in' => new SignedIn()]);

        self::assertSame($registry->get('signed_in'), $registry->get('signed_in'));
    }

    /**
     * @test
     */
    public function it_lists_every_kind_in_the_order_it_got_them(): void
    {
        $registry = $this->registry(['signed_in' => new SignedIn(), 'date_window' => new DateWindowKind()]);

        self::assertTrue($registry->has('signed_in'));
        self::assertFalse($registry->has('removed'));
        self::assertSame(['signed_in', 'date_window'], array_map(static fn (ConditionKindMetadata $kind): string => $kind->code, $registry->all()));
    }

    /**
     * @test
     */
    public function it_refuses_an_unknown_code(): void
    {
        $this->expectException(UnknownConditionKindException::class);
        $this->expectExceptionMessage('There is no condition kind with the code "removed"');

        $this->registry([])->get('removed');
    }

    /**
     * @test
     */
    public function it_refuses_a_class_without_the_attribute(): void
    {
        $registry = new ConditionKindRegistry(['params' => LocaleIsParams::class], new FieldReader(), new ServiceLocator([]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The class %s must carry #[AsConditionKind]', LocaleIsParams::class));

        $registry->get('params');
    }

    /**
     * @test
     */
    public function it_refuses_a_service_that_is_no_condition_kind(): void
    {
        $registry = new ConditionKindRegistry(['signed_in' => SignedIn::class], new FieldReader(), new ServiceLocator(['signed_in' => static fn (): \stdClass => new \stdClass()]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The condition kind "signed_in" must implement %s', ConditionKindInterface::class));

        $registry->get('signed_in');
    }

    /**
     * @param array<string, ConditionKindInterface> $kinds
     */
    private function registry(array $kinds): ConditionKindRegistry
    {
        return new ConditionKindRegistry(
            array_map(static fn (ConditionKindInterface $kind): string => $kind::class, $kinds),
            new FieldReader(),
            new ServiceLocator(array_map(static fn (ConditionKindInterface $kind): \Closure => static fn (): ConditionKindInterface => $kind, $kinds)),
        );
    }
}

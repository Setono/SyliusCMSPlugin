<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Setono\SyliusCMSPlugin\Rendering\FailureHandler;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Twig\Error\RuntimeError;

final class FailureHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const PAGE = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e40';

    private const SNIPPET = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e41';

    private const PLACEMENT = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e42';

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_logs_a_block_that_threw_at_error_with_its_entry_its_key_and_its_block_type_when_debug_is_off(): void
    {
        $exception = new \RuntimeException('The product is gone');
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error(Argument::containingString('{key}'), [
            'entry' => self::PAGE,
            'key' => self::KEY,
            'type' => 'heading',
            'message' => 'The product is gone',
            'exception' => $exception,
        ])->shouldBeCalledOnce();

        (new FailureHandler($logger->reveal(), false))->blockFailed(self::KEY, 'heading', self::PAGE, $exception);
    }

    /**
     * @test
     */
    public function it_throws_what_a_block_threw_when_debug_is_on_and_logs_nothing(): void
    {
        $exception = new \RuntimeException('The product is gone');
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error(Argument::cetera())->shouldNotBeCalled();

        try {
            (new FailureHandler($logger->reveal(), true))->blockFailed(self::KEY, 'heading', self::PAGE, $exception);
            self::fail('The exception was thrown');
        } catch (\RuntimeException $thrown) {
            self::assertSame($exception, $thrown);
        }
    }

    /**
     * @test
     */
    public function it_logs_the_message_of_the_exception_a_template_threw_rather_than_twigs_wrapper(): void
    {
        $exception = new RuntimeError('An exception has been thrown during the rendering of a template ("Boom").', previous: new \LogicException('Boom'));
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error(Argument::any(), Argument::withEntry('message', 'Boom'))->shouldBeCalledOnce();

        (new FailureHandler($logger->reveal(), false))->blockFailed(self::KEY, 'heading', self::PAGE, $exception);
    }

    /**
     * Such as the blocks the builder sends to be previewed, which aren't an entry's yet
     *
     * @test
     */
    public function it_logs_a_block_outside_an_entry_with_no_entry(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error(Argument::any(), Argument::withEntry('entry', null))->shouldBeCalledOnce();

        (new FailureHandler($logger->reveal(), false))->blockFailed(self::KEY, 'heading', null, new \RuntimeException('Boom'));
    }

    /**
     * @test
     */
    public function it_logs_a_snippet_that_threw_at_error_with_its_uuid_its_slot_and_its_placement_when_debug_is_off(): void
    {
        $exception = new \RuntimeException('No upgrader for version 7');
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error(Argument::containingString('{slot}'), [
            'entry' => self::SNIPPET,
            'slot' => 'shop.footer',
            'placement' => self::PLACEMENT,
            'message' => 'No upgrader for version 7',
            'exception' => $exception,
        ])->shouldBeCalledOnce();

        (new FailureHandler($logger->reveal(), false))->snippetFailed(self::placement(), $exception);
    }

    /**
     * @test
     */
    public function it_throws_what_a_snippet_threw_when_debug_is_on(): void
    {
        $exception = new \RuntimeException('No upgrader for version 7');
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->error(Argument::cetera())->shouldNotBeCalled();

        $this->expectExceptionObject($exception);

        (new FailureHandler($logger->reveal(), true))->snippetFailed(self::placement(), $exception);
    }

    private static function placement(): PlacementData
    {
        return new PlacementData(self::PLACEMENT, self::SNIPPET, 'shop.footer', [], 0);
    }
}

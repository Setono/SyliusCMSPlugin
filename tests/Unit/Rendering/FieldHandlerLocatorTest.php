<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class FieldHandlerLocatorTest extends TestCase
{
    /**
     * @test
     */
    public function it_gets_the_handler_a_field_names(): void
    {
        $handler = new TextHandler();
        $locator = new FieldHandlerLocator(new ServiceLocator([TextHandler::class => static fn (): TextHandler => $handler]));

        self::assertSame($handler, $locator->get(new Text(label: 'Heading')));
    }

    /**
     * @test
     */
    public function it_throws_when_the_handler_is_not_registered(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('"%s", which isn\'t registered as a field type handler', TextHandler::class));

        (new FieldHandlerLocator(new ServiceLocator([])))->get(new Text(label: 'Heading'));
    }

    /**
     * @test
     */
    public function it_throws_when_the_service_is_no_handler(): void
    {
        $this->expectException(\LogicException::class);

        (new FieldHandlerLocator(new ServiceLocator([TextHandler::class => static fn (): \stdClass => new \stdClass()])))->get(new Text(label: 'Heading'));
    }
}

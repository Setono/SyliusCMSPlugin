<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Generator\Twig;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Generator\Twig\GenericTwigGenerator;
use Setono\SyliusCMSPlugin\Model\Element;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class GenericTwigGeneratorTest extends TestCase
{
    /**
     * @test
     */
    public function it_generates_twig(): void
    {
        $environment = new Environment(new ArrayLoader([
            'template.html.twig' => '{{ element.code }}',
        ]));

        $element = new ConcreteElement();
        $element->setCode('foo');

        $generator = new GenericTwigGenerator($environment, ConcreteElement::class, 'template.html.twig');

        $twig = $generator->generate($element);

        $this->assertSame('foo', $twig);
    }
}

final class ConcreteElement extends Element
{
}

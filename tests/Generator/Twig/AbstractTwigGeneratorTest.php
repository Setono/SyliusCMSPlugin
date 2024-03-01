<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Generator\Twig;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Webmozart\Assert\Assert;

abstract class AbstractTwigGeneratorTest extends KernelTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_generates_twig(): void
    {
        $container = static::getContainer();
        $environment = $container->get('twig');
        Assert::isInstanceOf($environment, Environment::class);

        $generator = $this->getGenerator($environment);

        $twig = $generator->generate($this->getElement(), $this->getContext());

        $this->assertSame($this->getExpectedTwig(), $twig);
    }

    abstract protected function getGenerator(Environment $twig): TwigGeneratorInterface;

    abstract protected function getElement(): ElementInterface;

    /**
     * @return array<string, mixed>
     */
    protected function getContext(): array
    {
        return [];
    }

    abstract protected function getExpectedTwig(): string;
}

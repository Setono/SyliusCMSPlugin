<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link\Target;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Link\Target\UrlLinkTarget;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;

final class UrlLinkTargetTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_url_target_whose_ref_is_translatable(): void
    {
        $attribute = AttributeReader::linkTarget(UrlLinkTarget::class);

        self::assertNotNull($attribute);
        self::assertSame('url', $attribute->code);
        self::assertTrue($attribute->translatableRef);
    }

    /**
     * @test
     *
     * @dataProvider urls
     */
    public function it_resolves_to_the_url_as_typed(string $url, string $name): void
    {
        $link = (new UrlLinkTarget())->resolve($url, $this->context());

        self::assertNotNull($link);
        self::assertSame('url', $link->target);
        self::assertSame($url, $link->ref);
        self::assertSame($url, $link->url);
        self::assertSame($name, $link->name);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function urls(): iterable
    {
        yield 'https' => ['https://example.com/size-guide?unit=cm', 'https://example.com/size-guide?unit=cm'];
        yield 'http' => ['http://example.com', 'http://example.com'];
        yield 'mailto' => ['mailto:shop@example.com', 'shop@example.com'];
        yield 'tel' => ['tel:+4512345678', '+4512345678'];
        yield 'a path on the shop' => ['/en_US/taxons/t-shirts', '/en_US/taxons/t-shirts'];
        yield 'an anchor on the page' => ['#opening-hours', '#opening-hours'];
    }

    /**
     * @test
     *
     * @dataProvider refused
     */
    public function it_resolves_a_url_a_link_cannot_lead_to_to_nothing(string $url): void
    {
        self::assertNull((new UrlLinkTarget())->resolve($url, $this->context()));
    }

    /**
     * @test
     *
     * @dataProvider refused
     */
    public function publish_refuses_a_url_a_link_cannot_lead_to(string $url): void
    {
        self::assertSame('setono_sylius_cms.field.not_link_url', (new UrlLinkTarget())->refError($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'script' => ['javascript:alert(1)'];
        yield 'script in capitals' => ['JavaScript:alert(1)'];
        yield 'data' => ['data:text/html,<p>Hi</p>'];
        yield 'no scheme' => ['example.com'];
        yield 'spaces' => ['https://example.com/a page'];
        yield 'empty' => [''];
    }

    /**
     * @test
     */
    public function publish_accepts_a_url_a_link_can_lead_to(): void
    {
        self::assertNull((new UrlLinkTarget())->refError('mailto:shop@example.com'));
    }

    /**
     * @test
     */
    public function it_has_nothing_to_search(): void
    {
        self::assertSame([], [...(new UrlLinkTarget())->search('example', 'en_US')]);
    }

    private function context(): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable());
    }
}

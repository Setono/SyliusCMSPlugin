<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewChannelContext;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PreviewChannelContextTest extends TestCase
{
    use ProphecyTrait;

    private RequestStack $requestStack;

    private PreviewTokenSigner $signer;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->signer = new PreviewTokenSigner('secret', new MockClock());
    }

    /**
     * @test
     */
    public function it_gives_the_previews_channel(): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepository->findOneByCode('OTHER')->willReturn($channel);
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'OTHER', 'en_US'))]));

        self::assertSame($channel, $this->context($channelRepository->reveal())->getChannel());
    }

    /**
     * @test
     */
    public function it_leaves_a_shopper_request_to_the_other_channel_contexts(): void
    {
        $this->requestStack->push(new Request());
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepository->findOneByCode(Argument::any())->shouldNotBeCalled();

        $this->expectException(ChannelNotFoundException::class);

        $this->context($channelRepository->reveal())->getChannel();
    }

    /**
     * @test
     */
    public function it_has_no_channel_when_the_previews_channel_is_gone(): void
    {
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $channelRepository->findOneByCode('GONE')->willReturn(null);
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'GONE', 'en_US'))]));

        $this->expectException(ChannelNotFoundException::class);

        $this->context($channelRepository->reveal())->getChannel();
    }

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    private function context(ChannelRepositoryInterface $channelRepository): PreviewChannelContext
    {
        return new PreviewChannelContext(new PreviewContext($this->requestStack, $this->signer), $channelRepository);
    }
}

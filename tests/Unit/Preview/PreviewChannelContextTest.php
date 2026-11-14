<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
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

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channelRepository;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->signer = new PreviewTokenSigner('secret', new MockClock());
        $this->channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_gives_the_previews_channel(): void
    {
        $channel = $this->prophesize(ChannelInterface::class)->reveal();
        $this->channelRepository->findOneByCode('OTHER')->willReturn($channel);
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'OTHER', 'en_US'))]));

        self::assertSame($channel, $this->context()->getChannel());
    }

    /**
     * @test
     */
    public function it_leaves_a_shopper_request_to_the_other_channel_contexts(): void
    {
        $this->requestStack->push(new Request());
        $this->channelRepository->findOneByCode(Argument::any())->shouldNotBeCalled();

        $this->expectException(ChannelNotFoundException::class);

        $this->context()->getChannel();
    }

    /**
     * @test
     */
    public function it_has_no_channel_when_the_previews_channel_is_gone(): void
    {
        $this->channelRepository->findOneByCode('GONE')->willReturn(null);
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $this->signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'GONE', 'en_US'))]));

        $this->expectException(ChannelNotFoundException::class);

        $this->context()->getChannel();
    }

    private function context(): PreviewChannelContext
    {
        return new PreviewChannelContext(new PreviewContext($this->requestStack, $this->signer), $this->channelRepository->reveal());
    }
}

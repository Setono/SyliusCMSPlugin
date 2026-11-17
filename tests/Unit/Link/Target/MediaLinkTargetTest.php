<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Link\Target;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchResult;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Link\Target\MediaLinkTarget;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Media\PermanentUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;

final class MediaLinkTargetTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ReferenceTypeLoaderInterface> */
    private ObjectProphecy $mediaItems;

    /** @var ObjectProphecy<PermanentUrlGeneratorInterface> */
    private ObjectProphecy $permanentUrls;

    /** @var ObjectProphecy<SearchInterface> */
    private ObjectProphecy $search;

    protected function setUp(): void
    {
        $this->mediaItems = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $this->permanentUrls = $this->prophesize(PermanentUrlGeneratorInterface::class);
        $this->permanentUrls->generate(Argument::type(MediaItemInterface::class))->will(static function (array $arguments): ?string {
            $mediaItem = $arguments[0];
            \assert($mediaItem instanceof MediaItemInterface);

            return MediaKind::Image === $mediaItem->getKind() ? null : sprintf('https://localhost/media/%s/%s', $mediaItem->getUuid(), $mediaItem->getOriginalFilename());
        });
        $this->search = $this->prophesize(SearchInterface::class);
    }

    /**
     * @test
     */
    public function it_is_the_media_target_whose_ref_is_shared(): void
    {
        $attribute = AttributeReader::linkTarget(MediaLinkTarget::class);

        self::assertSame('media', $attribute?->code);
        self::assertFalse($attribute->translatableRef);
    }

    /**
     * @test
     */
    public function it_resolves_a_document_to_its_permanent_url_named_by_its_name(): void
    {
        $context = self::context();
        $document = self::mediaItem(MediaKind::Document, 'Size guide', 'size-guide.pdf');
        $this->mediaItems->load([$document->getUuid()], $context)->willReturn([$document->getUuid() => $document]);

        self::assertEquals(
            new ResolvedLink('media', $document->getUuid(), sprintf('https://localhost/media/%s/size-guide.pdf', $document->getUuid()), 'Size guide'),
            $this->target()->resolve($document->getUuid(), $context),
        );
    }

    /**
     * @test
     */
    public function it_resolves_a_video_to_its_permanent_url(): void
    {
        $context = self::context();
        $video = self::mediaItem(MediaKind::Video, 'Shop tour', 'shop-tour.mp4');
        $this->mediaItems->load([$video->getUuid()], $context)->willReturn([$video->getUuid() => $video]);

        self::assertEquals(
            new ResolvedLink('media', $video->getUuid(), sprintf('https://localhost/media/%s/shop-tour.mp4', $video->getUuid()), 'Shop tour'),
            $this->target()->resolve($video->getUuid(), $context),
        );
    }

    /**
     * @test
     */
    public function it_finds_the_media_item_by_its_uuid_in_lower_case_and_keeps_the_ref_as_stored(): void
    {
        $context = self::context();
        $document = self::mediaItem(MediaKind::Document, 'Size guide', 'size-guide.pdf');
        $this->mediaItems->load([$document->getUuid()], $context)->willReturn([$document->getUuid() => $document]);

        $link = $this->target()->resolve(strtoupper($document->getUuid()), $context);

        self::assertSame(strtoupper($document->getUuid()), $link?->ref);
    }

    /**
     * @test
     */
    public function it_resolves_an_image_which_has_no_permanent_url_to_nothing(): void
    {
        $context = self::context();
        $image = self::mediaItem(MediaKind::Image, 'Summer sale', 'summer-sale.jpg');
        $this->mediaItems->load([$image->getUuid()], $context)->willReturn([$image->getUuid() => $image]);

        self::assertNull($this->target()->resolve($image->getUuid(), $context));
    }

    /**
     * @test
     */
    public function it_resolves_a_deleted_media_item_and_a_ref_that_is_no_uuid_to_nothing(): void
    {
        $context = self::context();
        $this->mediaItems->load(['0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f'], $context)->willReturn([]);
        $this->mediaItems->load(Argument::not(['0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f']), Argument::any())->shouldNotBeCalled();

        self::assertNull($this->target()->resolve('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', $context));
        self::assertNull($this->target()->resolve('size-guide.pdf', $context));
    }

    /**
     * @test
     */
    public function it_refers_to_the_media_item_by_its_uuid_as_a_dependency_so_the_usage_index_counts_the_link(): void
    {
        self::assertEquals(
            new Reference('media_item', '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', true),
            $this->target()->reference('0193A5BE-6F1C-7D2E-9B41-6A8F0C2D3E4F'),
        );
        self::assertNull($this->target()->reference('size-guide.pdf'));
    }

    /**
     * @test
     */
    public function it_links_only_a_media_item_it_was_given(): void
    {
        self::assertNull($this->target()->link(new \stdClass(), '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', self::context()));
    }

    /**
     * @test
     */
    public function it_searches_documents_and_videos(): void
    {
        $this->search->search('size', 'en_US')->willReturn([new SearchResult('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'Size guide')]);

        self::assertSame(
            [['ref' => '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'name' => 'Size guide', 'thumbnail' => null]],
            [...$this->target()->search('size', 'en_US')],
        );
    }

    private function target(): MediaLinkTarget
    {
        return new MediaLinkTarget($this->mediaItems->reveal(), $this->permanentUrls->reveal(), $this->search->reveal());
    }

    private static function mediaItem(MediaKind $kind, string $name, string $originalFilename): MediaItemInterface
    {
        $mediaItem = new MediaItem();
        $mediaItem->setKind($kind);
        $mediaItem->setName($name);
        $mediaItem->setOriginalFilename($originalFilename);

        return $mediaItem;
    }

    private static function context(): RenderContext
    {
        return Rendering::context();
    }
}

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Model\MediaFolder;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Symfony\Component\Uid\Uuid;

/**
 * See "Metadata" in docs/spec/media.md
 */
final class MediaItemTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_a_uuid_and_its_focal_point_in_the_middle_from_the_start(): void
    {
        $mediaItem = new MediaItem();

        self::assertTrue(Uuid::isValid($mediaItem->getUuid()));
        self::assertNotSame($mediaItem->getUuid(), (new MediaItem())->getUuid());
        self::assertTrue($mediaItem->getFocalPoint()->equals(new FocalPoint(0.5, 0.5)));
        self::assertNull($mediaItem->getFolder());
        self::assertSame('UTC', $mediaItem->getChangedAt()->getTimezone()->getName());
    }

    /**
     * A locale without alt text has no translation, so the missing-alt filter and search only see real values
     *
     * @test
     */
    public function it_keeps_alt_text_per_locale_and_drops_an_empty_one(): void
    {
        $mediaItem = new MediaItem();
        $mediaItem->setAltText('en_US', 'A dog');
        $mediaItem->setAltText('de_DE', 'Ein Hund');
        $mediaItem->setAltText('da_DK', '  ');

        self::assertSame('A dog', $mediaItem->getAltText('en_US'));
        self::assertNull($mediaItem->getAltText('da_DK'));
        self::assertSame(['de_DE' => 'Ein Hund', 'en_US' => 'A dog'], $mediaItem->getAltTexts());
        self::assertCount(2, $mediaItem->getTranslations());

        $mediaItem->setAltText('de_DE', 'Ein brauner Hund');
        $mediaItem->setAltText('en_US', null);

        self::assertSame(['de_DE' => 'Ein brauner Hund'], $mediaItem->getAltTexts());
        self::assertCount(1, $mediaItem->getTranslations());
    }

    /**
     * @test
     */
    public function it_stores_its_times_in_utc(): void
    {
        $mediaItem = new MediaItem();
        $mediaItem->setChangedAt(new \DateTimeImmutable('2026-11-16 12:00:00', new \DateTimeZone('Europe/Copenhagen')));

        self::assertSame('2026-11-16T11:00:00+00:00', $mediaItem->getChangedAt()->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_lets_a_folder_contain_itself_and_the_folders_inside_it_at_any_depth(): void
    {
        $campaigns = new MediaFolder();
        $summer = new MediaFolder();
        $summer->setParent($campaigns);
        $beach = new MediaFolder();
        $beach->setParent($summer);
        $brand = new MediaFolder();

        self::assertTrue($campaigns->contains($campaigns));
        self::assertTrue($campaigns->contains($beach));
        self::assertFalse($beach->contains($campaigns));
        self::assertFalse($campaigns->contains($brand));
    }

    /**
     * @test
     *
     * @dataProvider outsideTheImage
     */
    public function it_keeps_a_focal_point_inside_the_image(float $x, float $y): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FocalPoint($x, $y);
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function outsideTheImage(): iterable
    {
        yield 'left of it' => [-0.1, 0.5];
        yield 'right of it' => [1.1, 0.5];
        yield 'above it' => [0.5, -0.01];
        yield 'below it' => [0.5, 1.5];
        yield 'nowhere' => [\NAN, 0.5];
    }
}

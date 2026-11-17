<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Doctrine\Persistence\ObjectManager;
use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;

/**
 * Editors drag files into a folder of the media library, give an image alt text and move its focal point, and find the
 * images still missing alt text. See "Media library" in docs/spec/admin.md and docs/spec/media.md
 */
final class MediaLibraryTest extends UITestCase
{
    use MediaItems;

    /**
     * @test
     */
    public function it_lets_an_editor_drag_two_images_into_a_new_folder_give_one_alt_text_and_a_focal_point_and_find_the_other_by_missing_alt_text(): void
    {
        $this->signInAsAdmin();

        // The hub's Library opens Media
        $browser = $this->visit('/admin/cms');
        $browser->getByRole('navigation', ['name' => 'Content'])->getByRole('link', ['name' => 'Media'])->click();
        Expect::locator($browser->getByRole('heading', ['name' => 'Media', 'level' => 1]))->toBeVisible();
        $folders = $browser->getByRole('navigation', ['name' => 'Folders']);
        Expect::locator($folders->getByRole('button', ['name' => 'All media']))->toHaveAttribute('aria-current', 'true');

        // A new folder opens
        $folders->getByRole('button', ['name' => 'New folder'])->click();
        $folders->getByLabel('Folder name')->fill('Campaigns');
        $folders->getByRole('button', ['name' => 'Create'])->click();
        Expect::locator($folders->getByRole('button', ['name' => 'Campaigns']))->toHaveAttribute('aria-current', 'true');

        // Two images dragged in go into it, flagged for their missing alt text
        $dropZone = $browser->getByTestId('media-drop-zone');
        Expect::locator($dropZone)->toContainText('Drop files to upload them into Campaigns');
        self::dropFiles($dropZone, ['beach.png' => self::png(80, 40, [30, 120, 200]), 'forest.jpg' => self::jpeg(60, 60, [20, 140, 40])]);

        $items = $dropZone->getByRole('listitem');
        Expect::locator($items)->toHaveCount(2);
        Expect::locator(self::item($dropZone, 'beach'))->toContainText('No alt text');
        Expect::locator(self::item($dropZone, 'forest'))->toContainText('No alt text');

        // One gets alt text and a focal point
        $beachUuid = self::item($dropZone, 'beach')->getAttribute('data-media-item');
        self::item($dropZone, 'beach')->click();
        $details = $browser->getByRole('complementary', ['name' => 'beach']);
        $details->getByLabel('English (United States)')->fill('Waves on a sandy beach');
        $area = $details->getByTestId('focal-point-area');
        $box = $area->boundingBox();
        self::assertNotNull($box);
        $area->click(['position' => ['x' => $box['width'] * 0.25, 'y' => $box['height'] * 0.75]]);
        $details->getByRole('button', ['name' => 'Save'])->click();
        Expect::locator($details->getByRole('status'))->toHaveText('Saved');
        Expect::locator(self::item($dropZone, 'beach'))->not()->toContainText('No alt text');

        // Only the other is missing alt text
        $browser->getByRole('checkbox', ['name' => 'Missing alt text'])->check();
        Expect::locator($items)->toHaveCount(1);
        Expect::locator(self::item($dropZone, 'forest'))->toBeVisible();

        // Saved at once, in the folder, with the focal point where it was put
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.media_item');
        $manager->clear();
        /** @var MediaItemRepositoryInterface $mediaItems */
        $mediaItems = self::getContainer()->get(MediaItemRepositoryInterface::class);
        self::assertIsString($beachUuid);
        $beach = $mediaItems->findOneByUuid($beachUuid);
        self::assertNotNull($beach);
        self::assertSame('Campaigns', $beach->getFolder()?->getName());
        self::assertSame('Waves on a sandy beach', $beach->getAltText('en_US'));
        self::assertEqualsWithDelta(0.25, $beach->getFocalPoint()->x, 0.02);
        self::assertEqualsWithDelta(0.75, $beach->getFocalPoint()->y, 0.02);
    }

    /**
     * @test
     */
    public function it_lets_an_editor_give_a_video_a_poster_picked_in_the_library_and_remove_it_again(): void
    {
        $video = $this->uploadMediaItem('launch-film.mp4', self::mp4());
        $still = $this->uploadMediaItem('launch-still.png', self::png(64, 36));
        $this->uploadMediaItem('size-guide.pdf', self::pdf());
        $this->signInAsAdmin();

        $browser = $this->visit('/admin/cms/media');
        $dropZone = $browser->getByTestId('media-drop-zone');
        self::item($dropZone, 'launch-film')->click();
        $details = $browser->getByRole('complementary', ['name' => 'launch-film']);
        $poster = $details->getByRole('group', ['name' => 'Poster']);
        Expect::locator($poster)->toContainText('Nothing chosen');

        // The library's own picker offers only images
        $poster->getByRole('button', ['name' => 'Choose from the library'])->click();
        $picker = $browser->getByRole('dialog', ['name' => 'Choose from the media library']);
        $pickerItems = $picker->getByTestId('media-drop-zone')->locator('[data-media-item]');
        Expect::locator($pickerItems)->toHaveCount(1);
        $pickerItems->filter(['hasText' => 'launch-still'])->click();
        $picker->getByRole('button', ['name' => 'Choose', 'exact' => true])->click();
        Expect::locator($picker)->toBeHidden();
        Expect::locator($poster)->toContainText('launch-still');

        $details->getByRole('button', ['name' => 'Save'])->click();
        Expect::locator($details->getByRole('status'))->toHaveText('Saved');
        self::assertSame($still->getUuid(), $this->reloaded($video->getUuid())->getPoster()?->getUuid());

        // Removed again
        $poster->getByRole('button', ['name' => 'Remove'])->click();
        $details->getByRole('button', ['name' => 'Save'])->click();
        Expect::locator($details->getByRole('status'))->toHaveText('Saved');
        Expect::locator($poster)->toContainText('Nothing chosen');
        self::assertNull($this->reloaded($video->getUuid())->getPoster());
    }

    private function reloaded(string $uuid): MediaItemInterface
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.media_item');
        $manager->clear();
        /** @var MediaItemRepositoryInterface $mediaItems */
        $mediaItems = self::getContainer()->get(MediaItemRepositoryInterface::class);

        return $mediaItems->findOneByUuid($uuid) ?? throw new \RuntimeException(sprintf('There is no media item %s', $uuid));
    }

    private static function item(LocatorInterface $dropZone, string $name): LocatorInterface
    {
        return $dropZone->locator('[data-media-item]')->filter(['hasText' => $name]);
    }

    /**
     * Drops files on the drop zone, as dragging them from the desktop does
     *
     * @param array<string, string> $files content by filename
     */
    private static function dropFiles(LocatorInterface $dropZone, array $files): void
    {
        $dropZone->evaluate(<<<'JS'
            (zone, files) => {
                const dataTransfer = new DataTransfer();
                for (const { name, type, content } of files) {
                    const bytes = Uint8Array.from(atob(content), (character) => character.charCodeAt(0));
                    dataTransfer.items.add(new File([bytes], name, { type }));
                }

                zone.dispatchEvent(new DragEvent('dragover', { bubbles: true, cancelable: true, dataTransfer }));
                zone.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer }));
            }
            JS, array_map(static fn (string $name, string $content): array => [
            'name' => $name,
            'type' => str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg',
            'content' => base64_encode($content),
        ], array_keys($files), $files));
    }
}

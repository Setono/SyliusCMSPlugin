<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Sylius\Bundle\FixturesBundle\Fixture\FixtureInterface;
use Sylius\Bundle\FixturesBundle\Fixture\FixtureRegistryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * The Sylius fixture setono_cms_import imports an export folder and publishes everything it imported, for demo and test
 * data. See "Fixture" in docs/spec/extending.md and "Who runs it" in docs/spec/transfer.md
 */
final class ImportFixtureTest extends WebTestCase
{
    use Entries;
    use Imports;
    use MediaItems;
    use TemporaryDirectory;

    /** The committed export's picked page, the snippet it shows and the snippet inside that one */
    private const ENTRIES = ['0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b', '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c'];

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    /** The banner, the video and its poster */
    private const MEDIA_ITEMS = ['0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60', '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61', '0193a5be-70ac-7c13-8e2d-1b2c3d4e5f62'];

    /**
     * @test
     */
    public function it_loads_a_committed_export_folder_and_publishes_everything_it_imported(): void
    {
        $client = self::createClient();

        self::load(['path' => self::exportFixture()]);

        self::entityManager()->clear();
        foreach (self::ENTRIES as $uuid) {
            $entry = self::entries()->findOneByUuid($uuid);
            self::assertInstanceOf(EntryInterface::class, $entry, $uuid);
            self::assertTrue($entry->isPublished(), sprintf('%s is published', $uuid));
        }

        self::assertSame('2026-11-12T10:15:00+00:00', self::entries()->findOneByUuid(self::ENTRIES[0])?->getChangedAt()->format(\DATE_ATOM));
        self::assertNotNull(self::presets()->find(self::PRESET));
        self::assertCount(3, array_filter(array_map(static fn (string $uuid): ?MediaItemInterface => self::mediaItems()->findOneByUuid($uuid), self::MEDIA_ITEMS)));

        // The shop shows the page
        $client->request('GET', '/en_US/autumn-sale');
        self::assertResponseIsSuccessful();
    }

    /**
     * @test
     */
    public function it_fails_the_load_with_the_reasons_when_the_import_refuses(): void
    {
        self::createPage('Autumn sale here', 'autumn-sale', published: true, channels: ['WEB']);

        try {
            self::load(['path' => self::exportFixture()]);
            self::fail('The load fails');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString(sprintf('The import of %s refused, so nothing changed:', self::exportFixture()), $e->getMessage());
            self::assertStringContainsString('Autumn sale would have the URL /en_US/autumn-sale, which Autumn sale here has in WEB.', $e->getMessage());
        }

        self::assertNull(self::entries()->findOneByUuid(self::ENTRIES[0]));
    }

    /**
     * @test
     */
    public function it_fails_the_load_for_a_path_that_is_not_an_export(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('%s is neither an export folder nor a zip', $this->temporaryDirectory() . '/missing'));

        self::load(['path' => $this->temporaryDirectory() . '/missing']);
    }

    /**
     * @test
     */
    public function it_needs_a_path(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::load([]);
    }

    /**
     * Loads the fixture the way a Sylius fixture suite does: found by its name, with its options processed
     *
     * @param array<string, mixed> $options
     */
    private static function load(array $options): void
    {
        /** @var FixtureRegistryInterface $fixtures */
        $fixtures = self::getContainer()->get('sylius_fixtures.fixture_registry');
        $fixture = $fixtures->getFixture('setono_cms_import');
        self::assertInstanceOf(FixtureInterface::class, $fixture);

        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration($fixture, [$options]);
        $fixture->load($processed);
    }

    private static function entries(): EntryRepositoryInterface
    {
        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);

        return $entries;
    }

    private static function mediaItems(): MediaItemRepositoryInterface
    {
        /** @var MediaItemRepositoryInterface $mediaItems */
        $mediaItems = self::getContainer()->get(MediaItemRepositoryInterface::class);

        return $mediaItems;
    }

    private static function presets(): PresetProviderInterface
    {
        /** @var PresetProviderInterface $presets */
        $presets = self::getContainer()->get(PresetProviderInterface::class);

        return $presets;
    }
}

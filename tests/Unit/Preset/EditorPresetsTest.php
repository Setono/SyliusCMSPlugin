<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Model\EditorPreset;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\EditorPresetDeleter;
use Setono\SyliusCMSPlugin\Preset\EditorPresetProvider;
use Setono\SyliusCMSPlugin\Preset\EditorPresetSaver;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\RecordingLogger;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Clock\MockClock;

/**
 * Editor presets in their rows: written in the preset file format with their UUID and changedAt, read back as presets,
 * saved at once, new or over the one with their UUID, and deleted. See "Editor presets" in
 * docs/spec/layouts-and-presets.md
 */
final class EditorPresetsTest extends TestCase
{
    use ProphecyTrait;

    private const UUID = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    private const KEY = '0193a5be-0000-7000-8000-000000000001';

    /** @var ObjectProphecy<EditorPresetRepositoryInterface> */
    private ObjectProphecy $repository;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $manager;

    protected function setUp(): void
    {
        $this->repository = $this->prophesize(EditorPresetRepositoryInterface::class);
        $this->repository->findOneByUuid(self::UUID)->willReturn(null);
        $this->manager = $this->prophesize(EntityManagerInterface::class);
    }

    /**
     * @test
     */
    public function it_writes_a_preset_to_its_row_in_the_preset_file_format_with_the_row_s_uuid_and_reads_it_back(): void
    {
        $row = new EditorPreset();
        $row->setUuid(self::UUID);

        (new EditorPresetContent())->write($row, self::preset('2026-11-10T08:00:00Z', uuid: null));

        self::assertSame('{"format":1,"uuid":"0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a","changedAt":"2026-11-10T08:00:00Z","name":"Promo","category":"Campaigns","description":null,"startingPoint":null,"blocks":[{"key":"0193a5be-0000-7000-8000-000000000001","type":"heading","version":1,"style":null,"fields":{"text":{"en_US":"Winter sale"}},"translated":{},"conditions":[],"children":[]}]}', $row->getContent());
        self::assertEquals(new \DateTimeImmutable('2026-11-10T08:00:00Z'), $row->getChangedAt());
        self::assertEquals(self::preset('2026-11-10T08:00:00Z'), (new EditorPresetContent())->read($row));
    }

    /**
     * @test
     */
    public function it_refuses_to_read_a_row_whose_content_is_another_preset(): void
    {
        $row = new EditorPreset();
        (new EditorPresetContent())->write($row, self::preset('2026-11-10T08:00:00Z'));
        $row->setUuid('0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0b');

        $this->expectException(InvalidPresetException::class);

        (new EditorPresetContent())->read($row);
    }

    /**
     * @test
     */
    public function it_saves_a_new_preset_with_a_new_uuid_and_now_to_the_second_as_its_changed_at(): void
    {
        $this->manager->persist(\Prophecy\Argument::type(EditorPreset::class))->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $saved = $this->saver('2026-11-20 10:00:00.654321')->save(self::preset('2026-01-01T00:00:00Z', uuid: null));

        self::assertNotNull($saved->uuid);
        self::assertNotSame(self::UUID, $saved->uuid);
        self::assertEquals(new \DateTimeImmutable('2026-11-20T10:00:00Z'), $saved->changedAt);
        self::assertSame('Promo', $saved->name);
    }

    /**
     * @test
     */
    public function it_saves_over_the_editor_preset_with_its_uuid(): void
    {
        $row = new EditorPreset();
        $row->setUuid(self::UUID);
        (new EditorPresetContent())->write($row, self::preset('2026-11-10T08:00:00Z'));
        $this->repository->findOneByUuid(self::UUID)->willReturn($row);
        $this->manager->persist($row)->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $saved = $this->saver('2026-11-20 10:00:00')->save(new Preset('Spring promo', [], uuid: self::UUID));

        self::assertSame(self::UUID, $saved->uuid);
        self::assertEquals($saved, (new EditorPresetContent())->read($row));
        self::assertSame('Spring promo', $saved->name);
    }

    /**
     * @test
     */
    public function it_creates_an_editor_preset_with_the_uuid_and_changed_at_it_is_given_as_an_import_does(): void
    {
        $this->manager->persist(\Prophecy\Argument::type(EditorPreset::class))->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $saved = $this->saver('2026-11-20 10:00:00')->save(self::preset('2026-01-01T00:00:00Z'), new \DateTimeImmutable('2026-11-10 09:00:00', new \DateTimeZone('Europe/Copenhagen')));

        self::assertSame(self::UUID, $saved->uuid);
        self::assertEquals(new \DateTimeImmutable('2026-11-10T08:00:00Z'), $saved->changedAt);
    }

    /**
     * @test
     */
    public function it_provides_the_editor_presets_it_can_read_and_finds_one_by_its_uuid_only(): void
    {
        $row = new EditorPreset();
        $row->setUuid(self::UUID);
        (new EditorPresetContent())->write($row, self::preset('2026-11-10T08:00:00Z'));
        $broken = new EditorPreset();
        $broken->setContent('{"format": 1}');
        $this->repository->findAllInCreationOrder()->willReturn([$row, $broken]);
        $this->repository->findOneByUuid(self::UUID)->willReturn($row);
        $logger = new RecordingLogger(new NullLogger());

        $provider = new EditorPresetProvider($this->repository->reveal(), $logger);

        self::assertEquals([self::preset('2026-11-10T08:00:00Z')], $provider->all());
        self::assertEquals(self::preset('2026-11-10T08:00:00Z'), $provider->find(self::UUID));
        self::assertNull($provider->find('hero'));
        self::assertSame([['uuid' => $broken->getUuid(), 'message' => 'A code preset must have a thumbnail, or null']], $logger->contextsAt('warning'));
    }

    /**
     * @test
     */
    public function it_deletes_the_editor_preset_with_the_uuid_and_says_whether_there_was_one(): void
    {
        $row = new EditorPreset();
        $this->repository->findOneByUuid(self::UUID)->willReturn($row);
        $this->repository->findOneByUuid('0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0b')->willReturn(null);
        $this->manager->remove($row)->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $deleter = new EditorPresetDeleter($this->repository->reveal(), $this->managerRegistry());

        self::assertTrue($deleter->delete(self::UUID));
        self::assertFalse($deleter->delete('0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0b'));
    }

    private function saver(string $now): EditorPresetSaver
    {
        return new EditorPresetSaver($this->repository->reveal(), new Factory(EditorPreset::class), $this->managerRegistry(), new MockClock($now, 'UTC'));
    }

    private function managerRegistry(): ManagerRegistry
    {
        $registry = $this->prophesize(ManagerRegistry::class);
        $registry->getManagerForClass(EditorPreset::class)->willReturn($this->manager->reveal());

        return $registry->reveal();
    }

    private static function preset(string $changedAt, ?string $uuid = self::UUID): Preset
    {
        return new Preset('Promo', [new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['en_US' => 'Winter sale']])], 'Campaigns', uuid: $uuid, changedAt: new \DateTimeImmutable($changedAt));
    }
}

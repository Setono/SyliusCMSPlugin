<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * setono:cms:preset:export writes an editor preset, by UUID or name, to <dir>/<code>.json, the code being its name in
 * snake case, with media fields left empty: a code preset file that setono:cms:preset:validate passes. See "Code
 * presets" in docs/spec/layouts-and-presets.md and "Console commands" in docs/spec/extending.md
 */
final class ExportPresetCommandTest extends KernelTestCase
{
    use Entries;
    use TemporaryDirectory;

    private const MEDIA = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    /**
     * @test
     */
    public function it_writes_an_editor_preset_by_its_uuid_as_a_code_preset_file_that_passes_validation(): void
    {
        $preset = self::save('Spring promo');
        $directory = $this->temporaryDirectory();

        $tester = self::command('setono:cms:preset:export', ['preset' => $preset->uuid, 'dir' => $directory]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $file = $directory . '/spring_promo.json';
        // SymfonyStyle wraps a long path where it likes
        self::assertStringContainsString('Wrote' . $file, (string) preg_replace('/\s+/', '', $tester->getDisplay()));
        self::assertFileExists($file);
        /** @var array{format: int, name: string, category: string, thumbnail: null, blocks: list<array{type: string, fields: array<string, mixed>}>} $written */
        $written = json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['format', 'name', 'category', 'description', 'thumbnail', 'startingPoint', 'blocks'], array_keys($written));
        self::assertSame(['Spring promo', 'Campaigns'], [$written['name'], $written['category']]);
        // The media field is left empty
        self::assertSame([['heading', ['text' => ['en_US' => 'Spring sale']]], ['image', ['caption' => ['en_US' => 'Our shop']]]], array_map(static fn (array $block): array => [$block['type'], $block['fields']], $written['blocks']));

        $validated = self::command('setono:cms:preset:validate', ['paths' => [$file]]);
        self::assertSame(Command::SUCCESS, $validated->getStatusCode(), $validated->getDisplay());
        self::assertStringContainsString('[OK] 1 preset file is valid.', self::display($validated));
    }

    /**
     * @test
     */
    public function it_finds_an_editor_preset_by_its_name_and_makes_the_directory(): void
    {
        self::save('Spring promo');
        $directory = $this->temporaryDirectory() . '/presets';

        $tester = self::command('setono:cms:preset:export', ['preset' => 'Spring promo', 'dir' => $directory]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertFileExists($directory . '/spring_promo.json');
    }

    /**
     * @test
     */
    public function it_exits_non_zero_for_a_preset_it_can_not_tell_apart_or_there_is_none_of(): void
    {
        $first = self::save('Promo');
        $second = self::save('Promo');

        $ambiguous = self::command('setono:cms:preset:export', ['preset' => 'Promo', 'dir' => $this->temporaryDirectory()]);
        self::assertSame(Command::FAILURE, $ambiguous->getStatusCode());
        self::assertStringContainsString(sprintf('There are 2 editor presets named "Promo": %s, %s. Export one by its UUID.', (string) $first->uuid, (string) $second->uuid), self::display($ambiguous));

        $unknown = self::command('setono:cms:preset:export', ['preset' => 'hero', 'dir' => $this->temporaryDirectory()]);
        self::assertSame(Command::FAILURE, $unknown->getStatusCode());
        self::assertStringContainsString('There is no editor preset with the UUID or name "hero".', self::display($unknown));
        self::assertSame([], glob($this->temporaryDirectory() . '/*.json'));
    }

    private static function save(string $name): Preset
    {
        self::bootKernel();
        /** @var EditorPresetSaverInterface $saver */
        $saver = self::getContainer()->get(EditorPresetSaverInterface::class);

        return $saver->save(new Preset($name, [
            self::heading('0193a5be-0000-7000-8000-000000000001', 'Spring sale'),
            new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'image', 1, fields: ['image' => self::MEDIA, 'caption' => ['en_US' => 'Our shop']]),
        ], 'Campaigns'));
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function command(string $command, array $input): CommandTester
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find($command));
        $tester->execute($input, ['decorated' => false]);

        return $tester;
    }

    /**
     * The command's output on one line, as SymfonyStyle wraps it
     */
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}

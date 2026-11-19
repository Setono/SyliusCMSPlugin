<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * setono:cms:preset:validate checks every code preset file in the registered directories, or the paths given, and exits
 * non-zero on any error, for CI. See "Code presets" in docs/spec/layouts-and-presets.md and "Console commands" in
 * docs/spec/extending.md
 */
final class ValidatePresetsCommandTest extends KernelTestCase
{
    use TemporaryDirectory;

    private const BROKEN = __DIR__ . '/Fixture/presets/broken';

    /**
     * @test
     */
    public function it_exits_zero_when_every_registered_file_is_valid(): void
    {
        $tester = self::validate([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $display = self::display($tester);
        self::assertStringContainsString('column_pair.json', $display);
        self::assertStringContainsString('sale_hero.json', $display);
        self::assertStringContainsString('product_spotlight.json', $display);
        // The plugin's shipped presets are registered too
        self::assertStringContainsString('src/Resources/presets/landing_page.json', $display);
        self::assertStringContainsString('[OK] 19 preset files are valid.', $display);
    }

    /**
     * @test
     */
    public function it_exits_non_zero_naming_each_error_of_the_files_given(): void
    {
        $tester = self::validate([self::BROKEN . '/retired.json', self::BROKEN . '/future.json', self::BROKEN . '/misfit.json', self::BROKEN . '/malformed.json']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = self::display($tester);
        self::assertStringContainsString('It has blocks of the type "gallery", which the code no longer has, so inserting it leaves them out.', $display);
        self::assertStringContainsString('0193a5be-0000-7000-8000-00000000d002', $display);
        self::assertStringContainsString('"heading" in the preset was saved with version 9, but this store has version 1, so the preset can\'t be inserted.', $display);
        self::assertStringContainsString('Heading isn\'t allowed in Columns.', $display);
        self::assertStringContainsString('Columns needs at least 2 Column blocks.', $display);
        self::assertStringContainsString('Level in the Heading block holds a value that doesn\'t fit its field type, so it counts as empty.', $display);
        self::assertStringContainsString('The preset must have the format 1', $display);
        self::assertStringContainsString('[ERROR] 4 of 4 preset files have errors.', $display);
    }

    /**
     * A preset is one or more saved blocks, and only one code preset is a content type's default starting point
     *
     * @test
     */
    public function it_exits_non_zero_for_a_preset_without_blocks_and_two_default_starting_points_of_one_content_type(): void
    {
        $empty = $this->temporaryFile('empty.json', self::presetFile([]));
        $landing = $this->temporaryFile('landing.json', self::presetFile(self::blocksOf('sale_hero'), ['contentType' => 'page', 'layout' => ['code' => 'standard', 'version' => 1, 'fields' => new \stdClass(), 'translated' => new \stdClass()], 'default' => true]));
        $launch = $this->temporaryFile('launch.json', self::presetFile(self::blocksOf('sale_hero'), ['contentType' => 'page', 'layout' => ['code' => 'standard', 'version' => 1, 'fields' => new \stdClass(), 'translated' => new \stdClass()], 'default' => true]));
        $about = $this->temporaryFile('about.json', self::presetFile(self::blocksOf('sale_hero'), ['contentType' => 'page', 'layout' => ['code' => 'standard', 'version' => 1, 'fields' => new \stdClass(), 'translated' => new \stdClass()], 'default' => false]));

        $tester = self::validate([$empty, $landing, $launch, $about]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        $display = self::display($tester);
        self::assertStringContainsString('It holds no blocks, so it has nothing to insert.', $display);
        self::assertStringContainsString('It\'s the default starting point of the content type "page", as launch.json is too. Only one code preset can be.', $display);
        self::assertStringContainsString('It\'s the default starting point of the content type "page", as landing.json is too. Only one code preset can be.', $display);
        self::assertStringContainsString('[ERROR] 3 of 4 preset files have errors.', $display);
    }

    /**
     * @test
     */
    public function it_validates_every_json_file_in_a_directory_given(): void
    {
        $tester = self::validate([self::BROKEN]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('[ERROR] 4 of 4 preset files have errors.', self::display($tester));
    }

    /**
     * @test
     */
    public function it_exits_non_zero_for_a_path_that_is_not_there_or_not_named_by_a_code(): void
    {
        $misnamed = $this->temporaryFile('Big-hero.json', (string) file_get_contents(dirname(__DIR__) . '/Application/config/cms/presets/column_pair.json'));

        $tester = self::validate([$this->temporaryDirectory() . '/missing.json', $misnamed]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = self::display($tester);
        self::assertStringContainsString('There is no file or directory here', $display);
        self::assertStringContainsString('A code preset file must be named by its code, which matches ^[a-z][a-z0-9_]*$', $display);
    }

    /**
     * @param list<string> $paths
     */
    private static function validate(array $paths): CommandTester
    {
        self::bootKernel();
        $tester = new CommandTester((new Application(self::$kernel))->find('setono:cms:preset:validate'));
        $tester->execute(['paths' => $paths], ['decorated' => false]);

        return $tester;
    }

    /**
     * The command's output on one line, as SymfonyStyle wraps it
     */
    /**
     * @param list<mixed> $blocks
     * @param array<string, mixed>|null $startingPoint
     */
    private static function presetFile(array $blocks, ?array $startingPoint = null): string
    {
        return (string) json_encode(['format' => 1, 'name' => 'A preset', 'category' => null, 'description' => null, 'thumbnail' => null, 'startingPoint' => $startingPoint, 'blocks' => $blocks], \JSON_THROW_ON_ERROR);
    }

    /**
     * The blocks of one of the test application's code presets
     *
     * @return list<mixed>
     */
    private static function blocksOf(string $code): array
    {
        /** @var array{blocks: list<mixed>} $preset */
        $preset = json_decode((string) file_get_contents(sprintf('%s/Application/config/cms/presets/%s.json', dirname(__DIR__), $code)), true, flags: \JSON_THROW_ON_ERROR);

        return $preset['blocks'];
    }

    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}

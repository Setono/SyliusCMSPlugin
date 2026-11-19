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
        self::assertStringContainsString('[OK] 14 preset files are valid.', $display);
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
    private static function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}

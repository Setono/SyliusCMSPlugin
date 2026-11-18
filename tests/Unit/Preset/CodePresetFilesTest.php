<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preset\CodePresetFiles;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;

/**
 * Every *.json file directly in a registered directory is a code preset, whose code is its file name. See "Code presets"
 * in docs/spec/layouts-and-presets.md
 */
final class CodePresetFilesTest extends TestCase
{
    use TemporaryDirectory;

    /**
     * @test
     */
    public function it_finds_the_json_files_directly_in_each_directory_by_their_code_in_the_order_the_directories_are_given(): void
    {
        $bundle = $this->directory('bundle', ['hero.json', 'faq.json', 'notes.txt', 'nested/deep.json']);
        $project = $this->directory('project', ['promo.json']);

        self::assertSame([
            'faq' => $bundle . '/faq.json',
            'hero' => $bundle . '/hero.json',
            'promo' => $project . '/promo.json',
        ], CodePresetFiles::in([$bundle, $project]));
    }

    /**
     * @test
     */
    public function it_finds_a_directory_given_twice_once(): void
    {
        $bundle = $this->directory('bundle', ['hero.json']);

        self::assertSame(['hero' => $bundle . '/hero.json'], CodePresetFiles::in([$bundle, $bundle . '/../bundle']));
    }

    /**
     * @test
     */
    public function it_refuses_two_files_with_the_same_code(): void
    {
        $bundle = $this->directory('bundle', ['hero.json']);
        $project = $this->directory('project', ['hero.json']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The code preset code "hero" is used by both %s/hero.json and %s/hero.json. Codes are unique across every directory in setono_sylius_cms.presets.paths', $bundle, $project));

        CodePresetFiles::in([$bundle, $project]);
    }

    /**
     * @test
     */
    public function it_refuses_a_file_whose_name_is_not_a_code(): void
    {
        $bundle = $this->directory('bundle', ['Big-hero.json']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The code preset file %s/Big-hero.json must be named by its code, which matches ^[a-z][a-z0-9_]*$', $bundle));

        CodePresetFiles::in([$bundle]);
    }

    /**
     * @test
     */
    public function it_refuses_a_directory_that_does_not_exist(): void
    {
        $missing = $this->temporaryDirectory() . '/missing';

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The directory %s in setono_sylius_cms.presets.paths doesn\'t exist', $missing));

        CodePresetFiles::in([$missing]);
    }

    /**
     * @param list<string> $files
     */
    private function directory(string $name, array $files): string
    {
        $directory = $this->temporaryDirectory() . '/' . $name;
        foreach ($files as $file) {
            if (!is_dir(dirname($directory . '/' . $file))) {
                mkdir(dirname($directory . '/' . $file), recursive: true);
            }

            file_put_contents($directory . '/' . $file, '{}');
        }

        return (string) realpath($directory);
    }
}

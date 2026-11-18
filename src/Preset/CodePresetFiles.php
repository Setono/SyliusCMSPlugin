<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Registry\Code;

/**
 * Finds the code preset files in directories: every *.json file directly in one is a code preset, whose code is its
 * file name without .json. Codes are unique across every directory, so a duplicate is refused, and the container build
 * that registers setono_sylius_cms.presets.paths fails. See "Code presets" in docs/spec/layouts-and-presets.md
 */
final class CodePresetFiles
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $directories in order: a bundle's first, as it prepends them, then the project's
     *
     * @return array<string, string> code => the file's real path, the directories' in order, each sorted by file name
     *
     * @throws \LogicException when a directory doesn't exist, a file isn't named by a code, or two files have the same code
     */
    public static function in(array $directories): array
    {
        $files = [];
        foreach (self::unique($directories) as $directory) {
            foreach (self::inDirectory($directory) as $code => $path) {
                if (isset($files[$code])) {
                    throw new \LogicException(sprintf('The code preset code "%s" is used by both %s and %s. Codes are unique across every directory in setono_sylius_cms.presets.paths', $code, $files[$code], $path));
                }

                $files[$code] = $path;
            }
        }

        return $files;
    }

    /**
     * @return array<string, string> code => the file's real path, sorted by file name
     *
     * @throws \LogicException when the directory doesn't exist or a file in it isn't named by a code
     */
    public static function inDirectory(string $directory): array
    {
        $real = realpath($directory);
        if (false === $real || !is_dir($real)) {
            throw new \LogicException(sprintf('The directory %s in setono_sylius_cms.presets.paths doesn\'t exist', $directory));
        }

        $paths = glob($real . '/*.json');
        if (false === $paths) {
            $paths = [];
        }

        sort($paths, \SORT_STRING);

        $files = [];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $code = basename($path, '.json');
            if (!Code::isValid($code)) {
                throw new \LogicException(sprintf('The code preset file %s must be named by its code, which matches %s', $path, trim(Code::PATTERN, '/')));
            }

            $files[$code] = $path;
        }

        return $files;
    }

    /**
     * The directories, each once, however it's written
     *
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private static function unique(array $directories): array
    {
        $unique = [];
        foreach ($directories as $directory) {
            $real = realpath($directory);
            $unique[false === $real ? $directory : $real] ??= $directory;
        }

        return array_values($unique);
    }
}

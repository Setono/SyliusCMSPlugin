<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

/**
 * Rewrites what a zip's headers say of a file, as an attacker could: a zip's directory, and each file's local header,
 * give its size before it's extracted, and nothing makes them tell the truth
 */
trait ZipHeaders
{
    /**
     * Sets the size the zip's local header and central directory give one of its files, whatever the file's real size
     */
    private static function declareSize(string $zip, string $name, int $size): void
    {
        $data = (string) file_get_contents($zip);
        $rewritten = 0;

        // A local file header (PK\3\4) has its uncompressed size at offset 22 and its name at 30, after the name's length at 26
        foreach (self::signatures($data, "PK\x03\x04") as $offset) {
            if ($name === self::name($data, $offset + 26, $offset + 30)) {
                $data = substr_replace($data, pack('V', $size), $offset + 22, 4);
                ++$rewritten;
            }
        }

        // A central directory file header (PK\1\2) has it at offset 24 and its name at 46, after the name's length at 28
        foreach (self::signatures($data, "PK\x01\x02") as $offset) {
            if ($name === self::name($data, $offset + 28, $offset + 46)) {
                $data = substr_replace($data, pack('V', $size), $offset + 24, 4);
                ++$rewritten;
            }
        }

        if (2 !== $rewritten) {
            throw new \LogicException(sprintf('The zip %s doesn\'t have one file %s', $zip, $name));
        }

        file_put_contents($zip, $data);
    }

    /**
     * @return list<int>
     */
    private static function signatures(string $data, string $signature): array
    {
        $offsets = [];
        $offset = 0;
        while (false !== ($found = strpos($data, $signature, $offset))) {
            $offsets[] = $found;
            $offset = $found + 4;
        }

        return $offsets;
    }

    private static function name(string $data, int $lengthOffset, int $nameOffset): string
    {
        /** @var array{1: int} $length */
        $length = unpack('v', substr($data, $lengthOffset, 2));

        return substr($data, $nameOffset, $length[1]);
    }
}

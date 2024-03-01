<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator;

use Symfony\Component\HttpFoundation\File\File;

final class PathGenerator implements PathGeneratorInterface
{
    public function fromFile(File $file): string
    {
        $ext = $file->guessExtension();

        return $this->expandPath(sprintf('%s%s', self::generateHash(), null === $ext ? '' : '.' . $ext));
    }

    private function expandPath(string $path): string
    {
        return sprintf('%s/%s/%s', substr($path, 0, 2), substr($path, 2, 2), substr($path, 4));
    }

    private static function generateHash(): string
    {
        do {
            $hash = bin2hex(random_bytes(16));
        } while (str_contains($hash, 'ad'));

        return $hash;
    }
}

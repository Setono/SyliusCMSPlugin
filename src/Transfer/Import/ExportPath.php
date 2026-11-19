<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * Whether a file's path in an export, such as a zip entry's name, resolves inside the export's root. An absolute path,
 * with a slash, a backslash or a drive letter first, and a path with a ".." segment, between slashes or backslashes,
 * don't. An export is untrusted input, so either refuses the import. See "Media files" in docs/spec/transfer.md
 */
final class ExportPath
{
    private function __construct()
    {
    }

    public static function isInsideRoot(string $path): bool
    {
        if ('' === $path || 1 === preg_match('#^([/\\\\]|[a-zA-Z]:)#', $path)) {
            return false;
        }

        return !in_array('..', preg_split('#[/\\\\]#', $path) ?: [], true);
    }
}

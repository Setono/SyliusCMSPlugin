<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

use Composer\InstalledVersions;

/**
 * The version of the plugin that's installed, which an export's manifest names for information, such as 2.0.0
 *
 * @internal
 */
final class PluginVersion
{
    public const PACKAGE = 'setono/sylius-cms-plugin';

    private function __construct()
    {
    }

    public static function installed(): string
    {
        $version = InstalledVersions::isInstalled(self::PACKAGE) ? InstalledVersions::getPrettyVersion(self::PACKAGE) : null;

        // A tag such as v2.0.0 is the version 2.0.0
        return null === $version ? 'unknown' : (string) preg_replace('/^v(?=\d)/', '', $version);
    }
}

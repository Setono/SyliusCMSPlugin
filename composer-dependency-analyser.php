<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    // The services use the framework's "clock" service, which exists only when symfony/clock is installed
    ->ignoreErrorsOnPackage('symfony/clock', [ErrorType::UNUSED_DEPENDENCY])
    // The enum case Reads::Locale (named in the spec) reads to older analysers (1.8.0, CI's lowest) as PHP's Locale
    // class from ext-intl. Newer ones read it right, and would then report this ignore as unmatched
    ->ignoreErrorsOnExtensionAndPath('ext-intl', __DIR__ . '/src/Condition/Reads.php', [ErrorType::SHADOW_DEPENDENCY])
    ->disableReportingUnmatchedIgnores()
;

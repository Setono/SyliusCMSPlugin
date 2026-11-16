<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    // The services use the framework's "clock" service, which exists only when symfony/clock is installed
    ->ignoreErrorsOnPackage('symfony/clock', [ErrorType::UNUSED_DEPENDENCY])
    // The enum case Reads::Locale (named in the spec) reads to the analyser as PHP's Locale class from ext-intl
    ->ignoreErrorsOnExtensionAndPath('ext-intl', __DIR__ . '/src/Condition/Reads.php', [ErrorType::SHADOW_DEPENDENCY])
;

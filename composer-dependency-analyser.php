<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    ->ignoreErrorsOnPackage('knplabs/knp-gaufrette-bundle', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('psr/cache', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('setono/editorjs-bundle', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('setono/editorjs-php', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('stof/doctrine-extensions-bundle', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('symfony/property-access', [ErrorType::UNUSED_DEPENDENCY])
;

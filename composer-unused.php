<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\NamedFilter;

return static function (Configuration $config): Configuration {
    return $config
        ->addNamedFilter(NamedFilter::fromString('setono/editorjs-bundle'))
        ->addNamedFilter(NamedFilter::fromString('stof/doctrine-extensions-bundle'))
        ->addNamedFilter(NamedFilter::fromString('symfony/mailer'))
    ;
};

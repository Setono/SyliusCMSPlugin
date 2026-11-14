<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import it without a prefix (docs/spec/extending.md#routes):
 *
 *     setono_sylius_cms_media:
 *         resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/media.php'
 *
 * It's empty until the media library adds the setono_sylius_cms_media route, so apps can import all three files from the start
 */
return static function (RoutingConfigurator $routes): void {
};

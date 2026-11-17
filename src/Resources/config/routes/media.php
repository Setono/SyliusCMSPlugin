<?php

declare(strict_types=1);

use Setono\SyliusCMSPlugin\Controller\Shop\PermanentUrlAction;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Requirement\Requirement;

/*
 * Import it without a prefix (docs/spec/extending.md#routes):
 *
 *     setono_sylius_cms_media:
 *         resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/media.php'
 *
 * The permanent URLs of documents and videos sit outside the locale prefix and are the same in every channel, because
 * media items are shared and public (docs/spec/media.md#permanent-urls). {uuid} must be a UUID, so the route never
 * catches other files under /media, such as Sylius's /media/image and Liip's /media/cache
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('setono_sylius_cms_media', '/media/{uuid}/{filename}')
        ->controller(PermanentUrlAction::class)
        ->methods(['GET'])
        ->requirements(['uuid' => Requirement::UUID, 'filename' => '[^/]+'])
    ;
};

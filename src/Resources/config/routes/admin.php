<?php

declare(strict_types=1);

use Setono\SyliusCMSPlugin\Controller\Admin\Api\PublishEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\RegistryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\SaveEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\ShowEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\BuilderAction;
use Setono\SyliusCMSPlugin\Controller\Admin\CreateEntryAction;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Requirement\Requirement;

/*
 * Import it under Sylius's admin path, so the CMS admin stays under the admin firewall (docs/spec/extending.md#routes):
 *
 *     setono_sylius_cms_admin:
 *         resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/admin.php'
 *         prefix: '/%sylius_admin.path_name%'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('setono_sylius_cms_admin_entry_create', '/cms/entries/{contentType}/new')
        ->controller(CreateEntryAction::class)
        ->methods(['GET', 'POST'])
        ->requirements(['contentType' => '[a-z][a-z0-9_]*'])
    ;

    $routes->add('setono_sylius_cms_admin_builder', '/cms/entry/{uuid}/builder')
        ->controller(BuilderAction::class)
        ->methods(['GET'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    // The builder's JSON endpoints are internal: their paths, names and bodies can change in any release (docs/spec/builder.md#json-endpoints)
    $routes->add('setono_sylius_cms_admin_api_registry', '/cms/api/registry')
        ->controller(RegistryAction::class)
        ->methods(['GET'])
    ;

    $routes->add('setono_sylius_cms_admin_api_entry_show', '/cms/api/entries/{uuid}')
        ->controller(ShowEntryAction::class)
        ->methods(['GET'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    $routes->add('setono_sylius_cms_admin_api_entry_save', '/cms/api/entries/{uuid}')
        ->controller(SaveEntryAction::class)
        ->methods(['PATCH'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    $routes->add('setono_sylius_cms_admin_api_entry_publish', '/cms/api/entries/{uuid}/publish')
        ->controller(PublishEntryAction::class)
        ->methods(['POST'])
        ->requirements(['uuid' => Requirement::UUID])
    ;
};

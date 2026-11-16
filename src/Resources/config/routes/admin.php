<?php

declare(strict_types=1);

use Setono\SyliusCMSPlugin\Controller\Admin\Api\DiscardEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\PresenceAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\PreviewRenderAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\PreviewTokenAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\PublishEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\RegistryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\RevisionIndexAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\SaveEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\SaveEntrySettingsAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\ShowEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\SlugAction;
use Setono\SyliusCMSPlugin\Controller\Admin\Api\ValidateEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\BuilderAction;
use Setono\SyliusCMSPlugin\Controller\Admin\CreateEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\DeleteEntryAction;
use Setono\SyliusCMSPlugin\Controller\Admin\DiscardEntryAction as DiscardEntryPageAction;
use Setono\SyliusCMSPlugin\Controller\Admin\OverviewAction;
use Setono\SyliusCMSPlugin\Controller\Admin\PublishEntryAction as PublishEntryPageAction;
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
    $routes->add('setono_sylius_cms_admin_overview', '/cms')
        ->controller(OverviewAction::class)
        ->methods(['GET'])
    ;

    // The entry lists are the grids of the entry resource, rendered by its resource controller (docs/spec/admin.md#entry-lists)
    $routes->add('setono_sylius_cms_admin_entry_index_all', '/cms/entries')
        ->controller('setono_sylius_cms.controller.entry::indexAction')
        ->methods(['GET'])
        ->defaults(['_sylius' => [
            'section' => 'admin',
            'permission' => true,
            'grid' => 'setono_sylius_cms_admin_entry_all',
            'template' => '@SetonoSyliusCMSPlugin/admin/entry/index.html.twig',
        ]])
    ;

    // A code no content type has fails the condition, so the request ends in a 404
    $routes->add('setono_sylius_cms_admin_entry_index', '/cms/entries/{contentType}')
        ->controller('setono_sylius_cms.controller.entry::indexAction')
        ->methods(['GET'])
        ->requirements(['contentType' => '[a-z][a-z0-9_]*'])
        ->condition("service('setono_sylius_cms_content_types').has(params['contentType'])")
        ->defaults(['_sylius' => [
            'section' => 'admin',
            'permission' => true,
            'grid' => 'setono_sylius_cms_admin_entry',
            'template' => '@SetonoSyliusCMSPlugin/admin/entry/index.html.twig',
        ]])
    ;

    $routes->add('setono_sylius_cms_admin_entry_create', '/cms/entries/{contentType}/new')
        ->controller(CreateEntryAction::class)
        ->methods(['GET', 'POST'])
        ->requirements(['contentType' => '[a-z][a-z0-9_]*'])
    ;

    // The entry's overview page, between its list and the builder, shown by the entry resource's controller (docs/spec/admin.md#entry-overview-page)
    $routes->add('setono_sylius_cms_admin_entry_show', '/cms/entry/{uuid}')
        ->controller('setono_sylius_cms.controller.entry::showAction')
        ->methods(['GET'])
        ->requirements(['uuid' => Requirement::UUID])
        ->defaults(['_sylius' => [
            'section' => 'admin',
            'permission' => true,
            'template' => '@SetonoSyliusCMSPlugin/admin/entry/show.html.twig',
            'repository' => ['method' => 'findOneByUuid', 'arguments' => ['$uuid']],
        ]])
    ;

    // The overview page's Publish and Discard changes, which come back to it
    $routes->add('setono_sylius_cms_admin_entry_publish', '/cms/entry/{uuid}/publish')
        ->controller(PublishEntryPageAction::class)
        ->methods(['POST'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    $routes->add('setono_sylius_cms_admin_entry_discard', '/cms/entry/{uuid}/discard')
        ->controller(DiscardEntryPageAction::class)
        ->methods(['POST'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    $routes->add('setono_sylius_cms_admin_entry_delete', '/cms/entry/{uuid}')
        ->controller(DeleteEntryAction::class)
        ->methods(['DELETE'])
        ->requirements(['uuid' => Requirement::UUID])
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

    $routes->add('setono_sylius_cms_admin_api_slug', '/cms/api/slug')
        ->controller(SlugAction::class)
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

    $routes->add('setono_sylius_cms_admin_api_entry_settings', '/cms/api/entries/{uuid}/settings')
        ->controller(SaveEntrySettingsAction::class)
        ->methods(['PUT'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    $routes->add('setono_sylius_cms_admin_api_entry_validate', '/cms/api/entries/{uuid}/validate')
        ->controller(ValidateEntryAction::class)
        ->methods(['POST'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    $routes->add('setono_sylius_cms_admin_api_entry_publish', '/cms/api/entries/{uuid}/publish')
        ->controller(PublishEntryAction::class)
        ->methods(['POST'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    $routes->add('setono_sylius_cms_admin_api_entry_discard', '/cms/api/entries/{uuid}/discard')
        ->controller(DiscardEntryAction::class)
        ->methods(['POST'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    $routes->add('setono_sylius_cms_admin_api_entry_presence', '/cms/api/entries/{uuid}/presence')
        ->controller(PresenceAction::class)
        ->methods(['POST'])
        ->requirements(['uuid' => Requirement::UUID])
    ;

    $routes->add('setono_sylius_cms_admin_api_preview_token', '/cms/api/preview/token')
        ->controller(PreviewTokenAction::class)
        ->methods(['POST'])
    ;

    $routes->add('setono_sylius_cms_admin_api_preview_render', '/cms/api/preview/render')
        ->controller(PreviewRenderAction::class)
        ->methods(['POST'])
    ;

    $routes->add('setono_sylius_cms_admin_api_revision_index', '/cms/api/entries/{uuid}/revisions')
        ->controller(RevisionIndexAction::class)
        ->methods(['GET'])
        ->requirements(['uuid' => Requirement::UUID])
    ;
};

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Layout;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\StoreLocation;

/**
 * The test application's layout of a store location's page, which prints its title, city and directions. A content
 * type with its own URL has a layout of its own
 */
#[Layout(
    code: 'store',
    contentType: StoreLocation::class,
    label: 'app.cms.layout.store',
    template: 'cms/layout/store.html.twig',
    default: true,
    shows: ['title', 'city', 'directions'],
)]
final class Store
{
}

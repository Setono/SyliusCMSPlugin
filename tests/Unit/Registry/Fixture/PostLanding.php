<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;

/**
 * The default post layout, with the code a page layout has too, which layout codes allow, because they're unique only within a content type
 */
#[Layout(code: 'landing', contentType: Post::class, label: 'app.post_landing', template: 'post_landing.html.twig', default: true)]
final class PostLanding
{
}

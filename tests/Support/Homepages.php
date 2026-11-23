<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Setono\SyliusCMSPlugin\Factory\HomepageFactory;
use Setono\SyliusCMSPlugin\Factory\HomepageFactoryInterface;
use Setono\SyliusCMSPlugin\Homepage\HomepageModifier;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * For unit tests: changes the channels a page is the homepage of with the shipped row model, as the container does when
 * a project doesn't override it
 */
final class Homepages
{
    /**
     * @param HomepageRepositoryInterface $repository where other pages' homepages are found, which only
     *                                                HomepageModifier::takeOffOtherPages() reads
     * @param TranslatorInterface $translator what its refusals are translated with
     * @param HomepageFactoryInterface|null $factory what creates the rows, or null for the shipped row model's factory
     */
    public static function modifier(
        HomepageRepositoryInterface $repository,
        TranslatorInterface $translator = new IdentityTranslator(),
        ?HomepageFactoryInterface $factory = null,
    ): HomepageModifier {
        return new HomepageModifier($factory ?? self::factory(), $repository, $translator);
    }

    public static function factory(): HomepageFactory
    {
        return new HomepageFactory(Homepage::class);
    }
}

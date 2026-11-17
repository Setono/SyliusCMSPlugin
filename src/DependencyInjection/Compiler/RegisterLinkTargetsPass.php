<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Builder\Search\LinkTargetSearch;
use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\LinkTargetRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hands the services carrying #[AsLinkTarget], which autoconfiguration tags, to the link target registry, by code, and
 * makes the builder's search endpoint search each one under its code, unless a search of that kind exists, as for the
 * products the product target links to. The registry, and so the builder, has them by their tag's priority, highest
 * first, which puts the shipped ones before a project's, and else in the order they're defined in. Two link targets
 * with the same code fail the container build, and so does a target that doesn't implement LinkTargetInterface.
 * See "Link targets" in docs/spec/extending.md
 */
final class RegisterLinkTargetsPass implements CompilerPassInterface
{
    public const TAG = 'setono_sylius_cms.link_target';

    public const SEARCH_TAG = 'setono_sylius_cms.search';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(LinkTargetRegistry::class)) {
            return;
        }

        /** @var array<string, class-string> $classes */
        $classes = [];

        /** @var array<string, Reference> $targets */
        $targets = [];

        $codeOf = static fn (string $class): ?string => AttributeReader::linkTarget($class)?->code;
        foreach (TaggedClasses::byCode($container, self::TAG, 'link target', '#[AsLinkTarget]', $codeOf, LinkTargetInterface::class) as $code => [$id, $class]) {
            $classes[$code] = $class;
            $targets[$code] = new Reference($id);
        }

        $container->getDefinition(LinkTargetRegistry::class)
            ->replaceArgument(0, $classes)
            ->replaceArgument(1, ServiceLocatorTagPass::register($container, $targets))
        ;

        $searched = [];
        foreach ($container->findTaggedServiceIds(self::SEARCH_TAG) as $tags) {
            foreach ($tags as $tag) {
                if (is_array($tag) && is_string($tag['kind'] ?? null)) {
                    $searched[$tag['kind']] = true;
                }
            }
        }

        foreach ($targets as $code => $target) {
            if (!isset($searched[$code])) {
                $container->setDefinition(sprintf('%s.%s', LinkTargetSearch::class, $code), (new Definition(LinkTargetSearch::class, [$target]))->addTag(self::SEARCH_TAG, ['kind' => $code]));
            }
        }
    }
}

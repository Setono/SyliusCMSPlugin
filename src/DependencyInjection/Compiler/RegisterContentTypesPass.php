<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Doctrine\DiscriminatorMapListener;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Content types are the Sylius resources whose model is an entry carrying #[ContentType] (ADR 0020).
 * This pass finds them in the registered resources and hands them to the content type registry,
 * the discriminator map listener and the create action's factory locator
 */
final class RegisterContentTypesPass implements CompilerPassInterface
{
    public const ENTRY_FACTORIES = 'setono_sylius_cms.entry_factories';

    private const CODE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ContentTypeRegistry::class) || !$container->hasParameter('sylius.resources')) {
            return;
        }

        /** @var array<string, array{model: class-string, translation: class-string|null, resource: string}> $contentTypes */
        $contentTypes = [];
        $factories = [];

        /** @var array<string, array{classes?: array{model?: mixed}, translation?: array{classes?: array{model?: mixed}}}> $resources */
        $resources = $container->getParameter('sylius.resources');

        foreach ($resources as $alias => $resource) {
            $model = $resource['classes']['model'] ?? null;
            if (!is_string($model) || !is_a($model, EntryInterface::class, true)) {
                continue;
            }

            $attribute = AttributeReader::contentType($model) ?? throw new \LogicException(sprintf('The resource "%s" is an entry, so its model %s must carry #[ContentType]', $alias, $model));
            $code = $attribute->code;

            if (1 !== preg_match(self::CODE_PATTERN, $code)) {
                throw new \LogicException(sprintf('The content type code "%s" of %s must match %s', $code, $model, self::CODE_PATTERN));
            }

            if (isset($contentTypes[$code])) {
                throw new \LogicException(sprintf('The content type code "%s" is used by both the resources "%s" and "%s"', $code, $contentTypes[$code]['resource'], $alias));
            }

            $translation = $resource['translation']['classes']['model'] ?? null;

            $contentTypes[$code] = [
                'model' => $model,
                'translation' => is_string($translation) && class_exists($translation) ? $translation : null,
                'resource' => $alias,
            ];

            [$applicationName, $resourceName] = explode('.', $alias, 2);
            $factories[$code] = new Reference(sprintf('%s.factory.%s', $applicationName, $resourceName));
        }

        $container->getDefinition(ContentTypeRegistry::class)->replaceArgument(0, $contentTypes);
        $container->getDefinition(DiscriminatorMapListener::class)->replaceArgument(0, $contentTypes);
        $container->setAlias(self::ENTRY_FACTORIES, (string) ServiceLocatorTagPass::register($container, $factories));
    }
}

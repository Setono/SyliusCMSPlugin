<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Attribute\Card;
use Setono\SyliusCMSPlugin\Doctrine\ColumnPrefixListener;
use Setono\SyliusCMSPlugin\Doctrine\DiscriminatorMapListener;
use Setono\SyliusCMSPlugin\Doctrine\RootAssociationsListener;
use Setono\SyliusCMSPlugin\Factory\EntryFactory;
use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\Code;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\EntryOrder;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Targeting\ChannelsAndAudienceModifierInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Content types are the Sylius resources whose model is an entry carrying #[ContentType] (ADR 0020).
 * This pass finds them in the registered resources and hands them to the content type registry, the Doctrine listeners
 * that map them and the create action's factory locator, and decorates their factories.
 * It hands the registry the URL prefixes set in setono_sylius_cms.content_types too, which override the attribute's
 */
final class RegisterContentTypesPass implements CompilerPassInterface
{
    public const ENTRY_FACTORIES = 'setono_sylius_cms.entry_factories';

    /** What the project changes about content types, by code: setono_sylius_cms.content_types */
    public const CONFIG_PARAMETER = 'setono_sylius_cms.content_types';

    /** The codes of the content types switched off: setono_sylius_cms.disabled.content_types */
    public const DISABLED_PARAMETER = 'setono_sylius_cms.disabled.content_types';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ContentTypeRegistry::class) || !$container->hasParameter('sylius.resources')) {
            return;
        }

        /** @var array<string, array{model: class-string, translation: class-string|null, resource: string}> $contentTypes */
        $contentTypes = [];
        $factories = [];
        /** @var array<string, string|null> $prefixes the attributes' prefixes, by content type code */
        $prefixes = [];
        /** @var array<string, class-string> $categoryTypes the category content types the attributes name, by content type code */
        $categoryTypes = [];

        /** @var array<string, array{classes?: array{model?: mixed}, translation?: array{classes?: array{model?: mixed}}}> $resources */
        $resources = $container->getParameter('sylius.resources');

        foreach ($resources as $alias => $resource) {
            $model = $resource['classes']['model'] ?? null;
            // The abstract root of the content types is a resource of its own, for the admin's entry lists
            if (!is_string($model) || !is_a($model, EntryInterface::class, true) || (new \ReflectionClass($model))->isAbstract()) {
                continue;
            }

            $attribute = AttributeReader::contentType($model) ?? throw new \LogicException(sprintf('The resource "%s" is an entry, so its model %s must carry #[ContentType]', $alias, $model));
            $code = $attribute->code;

            if (!Code::isValid($code)) {
                throw new \LogicException(sprintf('The content type code "%s" of %s must match %s', $code, $model, Code::PATTERN));
            }

            self::assertOrder($code, $model, $attribute->order);
            self::assertCards($code, $attribute->cards);

            if (isset($contentTypes[$code])) {
                throw new \LogicException(sprintf('The content type code "%s" is used by both the resources "%s" and "%s"', $code, $contentTypes[$code]['resource'], $alias));
            }

            $prefixes[$code] = $attribute->prefix;
            if (null !== $attribute->categoryType) {
                $categoryTypes[$code] = $attribute->categoryType;
            }

            $translation = $resource['translation']['classes']['model'] ?? null;
            $translation = is_string($translation) && class_exists($translation) ? $translation : null;

            // The meta description falls back to it through the fallback chain, so it's translatable
            if (null !== $attribute->descriptionField && (null === $translation || !property_exists($translation, $attribute->descriptionField))) {
                throw new \LogicException(sprintf('The content type "%s" names "%s" in descriptionField, which must be a property of its translations', $code, $attribute->descriptionField));
            }

            $contentTypes[$code] = [
                'model' => $model,
                'translation' => $translation,
                'resource' => $alias,
            ];

            [$applicationName, $resourceName] = explode('.', $alias, 2);
            $factory = sprintf('%s.factory.%s', $applicationName, $resourceName);
            $factories[$code] = new Reference($factory);

            // However an entry is created, it starts in every channel and in its content type's default layout
            $decorator = sprintf('setono_sylius_cms.entry_factory.%s', $code);
            $container->register($decorator, EntryFactory::class)
                ->setDecoratedService($factory)
                ->setArguments([
                    new Reference($decorator . '.inner'),
                    new Reference('sylius.repository.channel'),
                    new Reference(ChannelsAndAudienceModifierInterface::class),
                    new Reference(LayoutRegistryInterface::class),
                    $code,
                ])
            ;
        }

        self::assertCategoryTypes($categoryTypes, $contentTypes);

        $container->getDefinition(ContentTypeRegistry::class)
            ->replaceArgument(0, $contentTypes)
            ->setArgument(1, self::configuredPrefixes($container, $prefixes))
            ->setArgument(2, self::disabled($container, $contentTypes))
        ;
        $container->getDefinition(DiscriminatorMapListener::class)->replaceArgument(0, $contentTypes);
        foreach ([ColumnPrefixListener::class, RootAssociationsListener::class] as $listener) {
            if ($container->hasDefinition($listener)) {
                $container->getDefinition($listener)->replaceArgument(0, $contentTypes);
            }
        }
        $container->setAlias(self::ENTRY_FACTORIES, (string) ServiceLocatorTagPass::register($container, $factories));
    }

    /**
     * The prefixes the project set, by content type code. Only a content type with a URL of its own has a prefix to change
     *
     * @param array<string, string|null> $prefixes the attributes' prefixes, by content type code
     *
     * @return array<string, string>
     */
    private static function configuredPrefixes(ContainerBuilder $container, array $prefixes): array
    {
        /** @var array<string, array{prefix?: string}> $config */
        $config = $container->hasParameter(self::CONFIG_PARAMETER) ? $container->getParameter(self::CONFIG_PARAMETER) : [];

        $configured = [];
        foreach ($config as $code => $contentType) {
            if (!isset($contentType['prefix'])) {
                continue;
            }

            if (!array_key_exists($code, $prefixes)) {
                throw new \LogicException(sprintf('setono_sylius_cms.content_types sets a prefix for "%s", which is no content type', $code));
            }

            if (null === $prefixes[$code]) {
                throw new \LogicException(sprintf('setono_sylius_cms.content_types sets a prefix for "%s", which has no URL of its own', $code));
            }

            $configured[$code] = $contentType['prefix'];
        }

        return $configured;
    }

    /**
     * The order sources list the entries in is 'position', or properties of the entries, each 'ASC' or 'DESC'
     *
     * @param class-string $model
     */
    /**
     * Each display has one card, and is a code, as the Entries block prints it in its class, cms-entries--<display>
     *
     * @param list<Card> $cards
     */
    private static function assertCards(string $code, array $cards): void
    {
        $displays = [];
        foreach ($cards as $card) {
            if (!Code::isValid($card->display)) {
                throw new \LogicException(sprintf('The content type "%s" has a card for the display "%s", which must match %s', $code, $card->display, Code::PATTERN));
            }

            if (isset($displays[$card->display])) {
                throw new \LogicException(sprintf('The content type "%s" has more than one card for the display "%s"', $code, $card->display));
            }

            $displays[$card->display] = true;
        }
    }

    private static function assertOrder(string $code, string $model, mixed $order): void
    {
        try {
            $parsed = EntryOrder::parse($order);
        } catch (\InvalidArgumentException $e) {
            throw new \LogicException(sprintf('The content type "%s" has an invalid order: %s', $code, $e->getMessage()), previous: $e);
        }

        foreach (array_keys($parsed->fields) as $property) {
            if (!property_exists($model, $property)) {
                throw new \LogicException(sprintf('The content type "%s" orders its entries by "%s", which must be a property of %s', $code, $property, (new \ReflectionClass($model))->getShortName()));
            }
        }
    }

    /**
     * A collection's category content type is a content type of its own, named by its model or a class or interface the
     * model extends, as a project's model of a shipped one extends the shipped model. The collection holds its entries'
     * categories in one Entries field of its own of that content type, which is indexed, so listings filter on it
     * (docs/spec/collections.md#categories)
     *
     * @param array<string, class-string> $categoryTypes the category content type each content type names, by code
     * @param array<string, array{model: class-string, translation: class-string|null, resource: string}> $contentTypes
     */
    private static function assertCategoryTypes(array $categoryTypes, array $contentTypes): void
    {
        $models = array_values(array_column($contentTypes, 'model'));
        foreach ($categoryTypes as $code => $categoryType) {
            $categoryModel = self::modelNamedBy($categoryType, $models);
            if (null === $categoryModel) {
                throw new \LogicException(sprintf('The content type "%s" names %s in categoryType, which must be the model of a content type, or a class or interface one extends', $code, $categoryType));
            }

            $categoryFields = array_filter(
                (new FieldReader())->readContentType($contentTypes[$code]['model'], false),
                static fn (FieldMetadata $field): bool => $field->field instanceof Entries && $field->field->indexed && null !== $field->field->contentType && $categoryModel === self::modelNamedBy($field->field->contentType, $models),
            );

            if (1 !== count($categoryFields)) {
                throw new \LogicException(sprintf('The content type "%s" names %s in categoryType, so it must have one Entries field of its own with that contentType and indexed: true, which holds its categories', $code, $categoryType));
            }
        }
    }

    /**
     * The model a class names, as the content type registry finds it: the model that's the class, or else the first
     * that extends or implements it
     *
     * @param list<class-string> $models
     *
     * @return class-string|null
     */
    private static function modelNamedBy(string $class, array $models): ?string
    {
        if (in_array($class, $models, true)) {
            return $class;
        }

        foreach ($models as $model) {
            if (is_a($model, $class, true)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * The codes of the content types switched off in setono_sylius_cms.disabled.content_types. Any content type but
     * the built-in ones can be, such as the shipped collections' blog_post or faq_item
     *
     * @param array<string, mixed> $contentTypes by code
     *
     * @return list<string>
     */
    private static function disabled(ContainerBuilder $container, array $contentTypes): array
    {
        /** @var list<string> $disabled */
        $disabled = $container->hasParameter(self::DISABLED_PARAMETER) ? $container->getParameter(self::DISABLED_PARAMETER) : [];

        foreach ($disabled as $code) {
            if (in_array($code, ContentTypeMetadata::BUILT_IN, true)) {
                throw new \LogicException(sprintf('setono_sylius_cms.disabled.content_types switches off "%s", a built-in content type, which can\'t be switched off', $code));
            }

            if (!isset($contentTypes[$code])) {
                throw new \LogicException(sprintf('setono_sylius_cms.disabled.content_types switches off "%s", which is no content type', $code));
            }
        }

        return array_values(array_unique($disabled));
    }
}

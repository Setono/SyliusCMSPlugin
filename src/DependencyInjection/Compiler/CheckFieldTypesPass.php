<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistry;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\Code;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Registry\SourceRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks the field types: those the plugin ships, and those the fields of what's registered use, on block types,
 * layouts, content types, and the parameters of condition kinds and sources. A field type is declared by #[FieldType]
 * on its field attribute class, or a parent of it. Two field types with the same code fail the container build, as for
 * every other kind, and so does a project's own field type without a field control, which the builder couldn't show.
 * An Entries field declared indexed anywhere but on a content type's own model fails it too, since only such a field,
 * shared by the entry's translations, has its UUIDs written to the reference table. It runs after the passes that
 * register those classes. See "Adding a field type" and "Field types" in docs/spec/extending.md
 */
final class CheckFieldTypesPass implements CompilerPassInterface
{
    /**
     * A custom element's name, in ASCII: lowercase, starting with a letter, with a hyphen in it
     */
    private const CONTROL_PATTERN = '/^[a-z][a-z0-9._]*-[a-z0-9._-]*$/';

    private const SHIPPED_NAMESPACE = 'Setono\SyliusCMSPlugin\Field\\';

    public function process(ContainerBuilder $container): void
    {
        /** @var array<string, class-string> $declaredBy */
        $declaredBy = [];

        foreach (self::classesWithFields($container) as $where => $class) {
            self::assertIndexedOnlyOnContentTypes($class, $where);
        }

        foreach ([...self::shippedFieldAttributes(), ...self::fieldAttributes($container)] as $attribute) {
            [$class, $fieldType] = self::declaration($attribute);
            self::assertValid($class, $fieldType);

            $code = $fieldType->code;
            if (isset($declaredBy[$code]) && $declaredBy[$code] !== $class) {
                throw new \LogicException(sprintf('The field type code "%s" is used by both %s and %s. Codes are unique per kind, so give one of them another code', $code, $declaredBy[$code], $class));
            }

            $declaredBy[$code] = $class;
        }
    }

    /**
     * @param class-string $class
     */
    private static function assertValid(string $class, FieldType $fieldType): void
    {
        if (!Code::isValid($fieldType->code)) {
            throw new \LogicException(sprintf('The field type code "%s" of %s must match %s', $fieldType->code, $class, Code::PATTERN));
        }

        if (null === $fieldType->control) {
            if (!str_starts_with($class, self::SHIPPED_NAMESPACE)) {
                throw new \LogicException(sprintf('The field type %s needs a field control: name the custom element editors enter its values with in #[FieldType(control:)], and list the script that defines it under setono_sylius_cms.builder.scripts', $class));
            }

            return;
        }

        if (1 !== preg_match(self::CONTROL_PATTERN, $fieldType->control)) {
            throw new \LogicException(sprintf('The field control "%s" of the field type %s must be a custom element name: lowercase, starting with a letter and with a hyphen in it, such as app-opening-hours', $fieldType->control, $class));
        }
    }

    /**
     * @param class-string $class
     * @param string|null $where what the class is, or null for a content type's own model, whose fields are shared
     */
    private static function assertIndexedOnlyOnContentTypes(string $class, ?string $where): void
    {
        if (null === $where) {
            return;
        }

        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            foreach ($property->getAttributes(Entries::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                if ($attribute->newInstance()->indexed) {
                    throw new \LogicException(sprintf('The Entries field $%s of %s, %s, is declared indexed: true, but only a content type\'s own shared field is indexed, as the reference table holds a UUID by its entry and field. Remove indexed, or declare the field on the content type\'s model', $property->getName(), $class, $where));
                }
            }
        }
    }

    /**
     * The field attribute class's field type, and the class that declares it: the attribute class or a parent of it
     *
     * @param class-string<Field> $attribute
     *
     * @return array{class-string, FieldType}
     */
    private static function declaration(string $attribute): array
    {
        for ($class = new \ReflectionClass($attribute); false !== $class; $class = $class->getParentClass()) {
            foreach ($class->getAttributes(FieldType::class) as $fieldType) {
                return [$class->getName(), $fieldType->newInstance()];
            }
        }

        throw new \LogicException(sprintf('The field attribute %s must carry #[FieldType]', $attribute));
    }

    /**
     * The field attributes the plugin ships, whether a field uses them or not, so their codes are taken
     *
     * @return list<class-string<Field>>
     */
    private static function shippedFieldAttributes(): array
    {
        $attributes = [];
        $files = glob(dirname((string) (new \ReflectionClass(Field::class))->getFileName()) . '/*.php');
        foreach (false === $files ? [] : $files as $file) {
            $class = self::SHIPPED_NAMESPACE . basename($file, '.php');
            if (class_exists($class) && is_subclass_of($class, Field::class) && !(new \ReflectionClass($class))->isAbstract()) {
                $attributes[] = $class;
            }
        }

        return $attributes;
    }

    /**
     * The field attributes on the properties of every class with fields that's registered
     *
     * @return list<class-string<Field>>
     */
    private static function fieldAttributes(ContainerBuilder $container): array
    {
        $attributes = [];
        foreach (self::classesWithFields($container) as $class) {
            $reflection = $container->getReflectionClass($class) ?? throw new \LogicException(sprintf('The class %s, whose fields are checked, doesn\'t exist', $class));

            foreach ($reflection->getProperties() as $property) {
                foreach ($property->getAttributes(Field::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                    /** @var class-string<Field> $name */
                    $name = $attribute->getName();
                    $container->getReflectionClass($name);
                    $attributes[] = $name;
                }
            }
        }

        return $attributes;
    }

    /**
     * The block types, the layouts, the content types' entry and translation classes, and the parameter classes of the
     * condition kinds, sources and bindable properties, as the passes that register them leave them in their registries
     *
     * @return iterable<string|null, class-string> each class by what it is, or by null for a content type's own model
     */
    private static function classesWithFields(ContainerBuilder $container): iterable
    {
        foreach (self::strings(self::registered($container, BlockTypeRegistry::class)) as $class) {
            yield 'a block type' => $class;
        }

        foreach (self::registered($container, LayoutRegistry::class) as $layouts) {
            foreach (self::strings(is_array($layouts) ? $layouts : []) as $class) {
                yield 'a layout' => $class;
            }
        }

        foreach (self::registered($container, ContentTypeRegistry::class) as $contentType) {
            foreach (self::strings(is_array($contentType) ? [$contentType['model'] ?? null] : []) as $class) {
                yield null => $class;
            }

            foreach (self::strings(is_array($contentType) ? [$contentType['translation'] ?? null] : []) as $class) {
                yield 'a content type\'s translation, whose fields are translatable' => $class;
            }
        }

        $parameters = 'the parameters of a source or condition kind';
        foreach (self::strings(self::registered($container, ConditionKindRegistry::class)) as $class) {
            foreach (self::strings([AttributeReader::conditionKind($class)?->params]) as $params) {
                yield $parameters => $params;
            }
        }

        foreach (self::strings(self::registered($container, SourceRegistry::class)) as $class) {
            foreach (self::strings([AttributeReader::source($class)?->params]) as $params) {
                yield $parameters => $params;
            }
        }

        foreach (self::registered($container, BindablePropertyRegistry::class) as $declaration) {
            foreach (self::strings([is_array($declaration) ? $declaration['params'] ?? null : null]) as $params) {
                yield 'the parameters of a bindable property' => $params;
            }
        }
    }

    /**
     * What the registry was handed, by code: its first argument
     *
     * @return array<mixed>
     */
    private static function registered(ContainerBuilder $container, string $registry): array
    {
        if (!$container->hasDefinition($registry)) {
            return [];
        }

        $arguments = $container->getDefinition($registry)->getArguments();
        $registered = $arguments[0] ?? [];

        return is_array($registered) ? $registered : [];
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<class-string>
     */
    private static function strings(array $values): array
    {
        /** @var list<class-string> $strings */
        $strings = array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && class_exists($value)));

        return $strings;
    }
}

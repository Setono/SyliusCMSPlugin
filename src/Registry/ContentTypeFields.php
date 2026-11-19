<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Doctrine\Persistence\Proxy;
use Setono\SyliusCMSPlugin\Field\DateTimeHandler;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Model\UtcColumn;

/**
 * A content type's own fields, beside the base fields every entry has (EntryFields): the mapped properties with a field
 * attribute of its model, which are shared by all translations, and of its translation model, which are translatable.
 * It reads and writes their values in the field value encoding the entry format stores them in, from and to what the
 * properties hold as Doctrine maps them, such as a Date field's DateTimeImmutable or a Media field's UUID string.
 * A value that doesn't fit its property counts as missing ("Values that don't fit" in docs/spec/content-model.md).
 * See "Content types" in docs/spec/extending.md
 */
final class ContentTypeFields
{
    /** @var array<string, array<string, FieldMetadata>> class and whether it's a translation's => its fields */
    private array $fields = [];

    public function __construct(private readonly FieldReader $fieldReader)
    {
    }

    /**
     * The fields its model declares, shared by all translations
     *
     * @return array<string, FieldMetadata> property name => field, in declaration order
     */
    public function shared(ContentTypeMetadata $contentType): array
    {
        return $this->of($contentType->modelClass, false);
    }

    /**
     * The fields its translation model declares, translatable. A content type without translatable fields of its own
     * has none
     *
     * @return array<string, FieldMetadata> property name => field, in declaration order
     */
    public function translatable(ContentTypeMetadata $contentType): array
    {
        $class = $contentType->translationModelClass;

        return null === $class ? [] : $this->of($class, true);
    }

    /**
     * The field of an entry, for a shared field, or of one of its translations, for a translatable one
     *
     * @throws \InvalidArgumentException when it's not one of the fields of the subject's class
     */
    public function field(object $subject, string $name): FieldMetadata
    {
        return $this->of($subject::class, $subject instanceof EntryTranslationInterface)[$name]
            ?? throw new \InvalidArgumentException(sprintf('"%s" is not one of the fields of %s', $name, $subject::class));
    }

    /**
     * A field's value as the entry format stores it: a date as "2026-11-13", a date and time in UTC as
     * "2026-11-13T09:30:00Z", and everything else as the property holds it
     *
     * @param object $subject an entry, for a shared field, or one of its translations, for a translatable one
     *
     * @throws \InvalidArgumentException when it's not one of the fields of the subject's class
     */
    public function read(object $subject, string $name): mixed
    {
        $field = $this->field($subject, $name);
        $property = self::property($subject, $name);

        $value = $property->isInitialized($subject) ? $property->getValue($subject) : null;
        if (!$value instanceof \DateTimeInterface) {
            return $value;
        }

        // The plugin writes dates and times in UTC, and Doctrine hydrates them in PHP's default time zone
        $value = UtcColumn::read(\DateTimeImmutable::createFromInterface($value));

        return 'date' === $field->type->code ? $value->format('Y-m-d') : $value->format(DateTimeHandler::FORMAT);
    }

    /**
     * Writes a value the entry format stores into the field's property, as Doctrine maps it. A missing value, or one
     * that doesn't fit the property, gives the property its default: null, or the value it's declared with
     *
     * @param object $subject an entry, for a shared field, or one of its translations, for a translatable one
     *
     * @throws \InvalidArgumentException when it's not one of the fields of the subject's class
     */
    public function write(object $subject, string $name, mixed $value): void
    {
        $field = $this->field($subject, $name);
        $property = self::property($subject, $name);

        if (is_string($value) && self::takesDates($property)) {
            $value = self::date($field, $value);
        }

        if (null !== $value) {
            try {
                $property->setValue($subject, $value);

                return;
            } catch (\TypeError) {
                // It doesn't fit, so it's missing
            }
        }

        if ($property->getType()?->allowsNull() ?? true) {
            $property->setValue($subject, null);
        } elseif ($property->hasDefaultValue()) {
            $property->setValue($subject, $property->getDefaultValue());
        }
    }

    /**
     * @param class-string $class
     *
     * @return array<string, FieldMetadata>
     */
    private function of(string $class, bool $translation): array
    {
        return $this->fields[$class . ($translation ? '#translation' : '')] ??= $this->fieldReader->readContentType($class, $translation);
    }

    /**
     * The property, which can be private to a class the subject's class extends, such as a shipped model a project
     * subclasses. A Doctrine proxy is loaded first, so its properties hold the row's values
     */
    private static function property(object $subject, string $name): \ReflectionProperty
    {
        if ($subject instanceof Proxy && !$subject->__isInitialized()) {
            $subject->__load();
        }

        for ($class = new \ReflectionClass($subject); false !== $class; $class = $class->getParentClass()) {
            if ($class->hasProperty($name) && $class->getProperty($name)->getDeclaringClass()->getName() === $class->getName()) {
                return $class->getProperty($name);
            }
        }

        throw new \InvalidArgumentException(sprintf('%s has no property "%s"', $subject::class, $name));
    }

    /**
     * Whether the property holds a date and time object, as Doctrine maps a date or datetime column
     */
    private static function takesDates(\ReflectionProperty $property): bool
    {
        $type = $property->getType();
        $types = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];

        foreach ($types as $named) {
            if ($named instanceof \ReflectionNamedType && in_array($named->getName(), [\DateTimeImmutable::class, \DateTimeInterface::class], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The date, at midnight UTC, or the date and time in UTC, that a stored value is, or null when it's neither
     */
    private static function date(FieldMetadata $field, string $value): ?\DateTimeImmutable
    {
        $format = 'date' === $field->type->code ? '!Y-m-d' : DateTimeHandler::FORMAT;
        $date = \DateTimeImmutable::createFromFormat($format, $value, new \DateTimeZone('UTC'));

        return false !== $date && $date->format(ltrim($format, '!')) === $value ? $date : null;
    }
}

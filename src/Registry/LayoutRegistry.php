<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * Layouts are registered as metadata, not as services, because they're hydrated per use. The container hands this
 * registry the layout classes by content type and code, and it reads their attributes on first use. RegisterLayoutsPass
 * asks it for each content type's default while the container builds, so the configuration errors it throws fail the build
 */
final class LayoutRegistry implements LayoutRegistryInterface
{
    /** @var array<string, array<string, LayoutMetadata>> */
    private array $layouts = [];

    /** @var array<string, string> content type code => the code of its default layout */
    private array $defaultCodes = [];

    /**
     * @param array<string, array<string, class-string>> $classes content type code => layout code => class, in the order they were registered
     * @param array<string, string> $defaults content type code => the code of the default layout setono_sylius_cms.content_types.<code>.default_layout names
     * @param list<string> $disabled the layouts switched off in setono_sylius_cms.disabled.layouts, as "<content type code>.<layout code>"
     */
    public function __construct(
        private readonly array $classes,
        private readonly FieldReader $fieldReader,
        private readonly array $defaults = [],
        private readonly array $disabled = [],
    ) {
    }

    public function has(string $contentType, string $code): bool
    {
        return isset($this->classes[$contentType][$code]);
    }

    public function get(string $contentType, string $code): LayoutMetadata
    {
        if (!isset($this->classes[$contentType][$code])) {
            throw new UnknownLayoutException($contentType, $code);
        }

        return $this->layouts[$contentType][$code] ??= $this->read($contentType, $code, $this->classes[$contentType][$code]);
    }

    public function all(string $contentType): array
    {
        if (!isset($this->classes[$contentType])) {
            return [];
        }

        $default = $this->defaultCode($contentType);
        $codes = array_keys($this->classes[$contentType]);
        usort($codes, static fn (string $a, string $b): int => ($b === $default) <=> ($a === $default));

        return array_map(fn (string $code): LayoutMetadata => $this->get($contentType, $code), $codes);
    }

    public function getDefault(string $contentType): LayoutMetadata
    {
        if (!isset($this->classes[$contentType])) {
            throw new UnknownLayoutException($contentType);
        }

        return $this->get($contentType, $this->defaultCode($contentType));
    }

    public function resolve(string $contentType, ?string $code): ?LayoutMetadata
    {
        if (!isset($this->classes[$contentType])) {
            return null;
        }

        if (null !== $code && $this->has($contentType, $code) && !$this->get($contentType, $code)->disabled) {
            return $this->get($contentType, $code);
        }

        return $this->getDefault($contentType);
    }

    /**
     * @param class-string $class
     */
    private function read(string $contentType, string $code, string $class): LayoutMetadata
    {
        $attribute = AttributeReader::layout($class) ?? throw new \LogicException(sprintf('The class %s must carry #[Layout]', $class));

        return new LayoutMetadata(
            $code,
            $contentType,
            $attribute->version,
            $class,
            $this->fieldReader->read($class),
            $attribute->label,
            $attribute->template,
            $attribute->thumbnail,
            $attribute->shows,
            $this->isDisabled($contentType, $code),
        );
    }

    /**
     * The layout the config names, which always wins, or else the one layout marked #[Layout(default: true)]
     */
    private function defaultCode(string $contentType): string
    {
        if (isset($this->defaultCodes[$contentType])) {
            return $this->defaultCodes[$contentType];
        }

        $code = $this->defaults[$contentType] ?? $this->markedDefault($contentType);
        if (!isset($this->classes[$contentType][$code])) {
            throw new \LogicException(sprintf('setono_sylius_cms.content_types.%s.default_layout names the layout "%s", which the content type "%s" doesn\'t have', $contentType, $code, $contentType));
        }

        if ($this->isDisabled($contentType, $code)) {
            throw new \LogicException(sprintf('The layout "%s" is the default layout of the content type "%s", so it can\'t be disabled in setono_sylius_cms.disabled.layouts', $code, $contentType));
        }

        return $this->defaultCodes[$contentType] = $code;
    }

    private function markedDefault(string $contentType): string
    {
        $marked = array_filter($this->classes[$contentType] ?? [], static fn (string $class): bool => true === AttributeReader::layout($class)?->default);

        return match (count($marked)) {
            1 => (string) array_key_first($marked),
            0 => throw new \LogicException(sprintf('The content type "%1$s" has no default layout. Mark one of its layouts #[Layout(default: true)], or name one in setono_sylius_cms.content_types.%1$s.default_layout', $contentType)),
            default => throw new \LogicException(sprintf('The content type "%1$s" has more than one default layout: %2$s. Remove default: true from all but one, or name one in setono_sylius_cms.content_types.%1$s.default_layout', $contentType, implode(' and ', $marked))),
        };
    }

    private function isDisabled(string $contentType, string $code): bool
    {
        return in_array(sprintf('%s.%s', $contentType, $code), $this->disabled, true);
    }
}

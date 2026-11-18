<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Content types are the Sylius resources whose model carries #[ContentType]. The container hands this registry
 * their model classes by code, the URL prefixes the project set in setono_sylius_cms.content_types, and the codes of
 * those switched off in setono_sylius_cms.disabled.content_types, and it reads their attributes on first use
 */
final class ContentTypeRegistry implements ContentTypeRegistryInterface
{
    /** @var array<string, ContentTypeMetadata> */
    private array $contentTypes = [];

    /**
     * @param array<string, array{model: class-string<EntryInterface>, translation: class-string|null, resource: string}> $resources content type code => its resource
     * @param array<string, string> $prefixes content type code => the URL prefix the project set, in place of the attribute's
     * @param list<string> $disabled the codes of the content types switched off
     */
    public function __construct(
        private readonly array $resources,
        private readonly array $prefixes = [],
        private readonly array $disabled = [],
    ) {
    }

    public function has(string $code): bool
    {
        return isset($this->resources[$code]);
    }

    public function isEnabled(string $code): bool
    {
        return $this->has($code) && !in_array($code, $this->disabled, true);
    }

    public function get(string $code): ContentTypeMetadata
    {
        if (!isset($this->resources[$code])) {
            throw new UnknownContentTypeException(sprintf('There is no content type with the code "%s"', $code));
        }

        return $this->contentTypes[$code] ??= self::read($this->resources[$code], $this->prefixes[$code] ?? null, in_array($code, $this->disabled, true));
    }

    public function getByEntry(EntryInterface $entry): ContentTypeMetadata
    {
        foreach ($this->resources as $code => $resource) {
            if ($entry instanceof $resource['model']) {
                return $this->get($code);
            }
        }

        throw new UnknownContentTypeException(sprintf('The entry class %s is no registered content type', $entry::class));
    }

    public function all(): array
    {
        return array_map($this->get(...), array_keys($this->resources));
    }

    public function enabled(): array
    {
        return array_values(array_filter($this->all(), static fn (ContentTypeMetadata $contentType): bool => !$contentType->disabled));
    }

    /**
     * @param array{model: class-string<EntryInterface>, translation: class-string|null, resource: string} $resource
     * @param string|null $prefix the prefix the project set, or null for the attribute's
     */
    private static function read(array $resource, ?string $prefix, bool $disabled): ContentTypeMetadata
    {
        $attribute = AttributeReader::contentType($resource['model']) ?? throw new \LogicException(sprintf('The class %s must carry #[ContentType]', $resource['model']));

        return new ContentTypeMetadata(
            $attribute->code,
            $attribute->label,
            $attribute->version,
            $prefix ?? $attribute->prefix,
            $attribute->blocks,
            $resource['model'],
            $resource['translation'],
            $resource['resource'],
            $attribute->descriptionField,
            EntryOrder::parse($attribute->order),
            $attribute->cards,
            $attribute->categoryType,
            $disabled,
        );
    }
}

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Content types are the Sylius resources whose model carries #[ContentType]. The container hands this registry
 * their model classes by code, and it reads their attributes on first use
 */
final class ContentTypeRegistry implements ContentTypeRegistryInterface
{
    /** @var array<string, ContentTypeMetadata> */
    private array $contentTypes = [];

    /**
     * @param array<string, array{model: class-string<EntryInterface>, translation: class-string|null, resource: string}> $resources content type code => its resource
     */
    public function __construct(private readonly array $resources)
    {
    }

    public function has(string $code): bool
    {
        return isset($this->resources[$code]);
    }

    public function get(string $code): ContentTypeMetadata
    {
        if (!isset($this->resources[$code])) {
            throw new UnknownContentTypeException(sprintf('There is no content type with the code "%s"', $code));
        }

        return $this->contentTypes[$code] ??= self::read($this->resources[$code]);
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

    /**
     * @param array{model: class-string<EntryInterface>, translation: class-string|null, resource: string} $resource
     */
    private static function read(array $resource): ContentTypeMetadata
    {
        $attribute = AttributeReader::contentType($resource['model']) ?? throw new \LogicException(sprintf('The class %s must carry #[ContentType]', $resource['model']));

        return new ContentTypeMetadata(
            $attribute->code,
            $attribute->label,
            $attribute->version,
            $attribute->prefix,
            $attribute->blocks,
            $resource['model'],
            $resource['translation'],
            $resource['resource'],
        );
    }
}

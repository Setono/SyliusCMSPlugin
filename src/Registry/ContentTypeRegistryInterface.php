<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The content types the code has, the disabled ones included, whose entries keep being read. A disabled content type
 * is hidden from the admin, the builder, sources and routing, which go by enabled() and isEnabled()
 */
interface ContentTypeRegistryInterface
{
    public function has(string $code): bool;

    /**
     * Whether the code has the content type and it isn't switched off in setono_sylius_cms.disabled.content_types
     */
    public function isEnabled(string $code): bool;

    /**
     * @throws UnknownContentTypeException
     */
    public function get(string $code): ContentTypeMetadata;

    /**
     * @throws UnknownContentTypeException
     */
    public function getByEntry(EntryInterface $entry): ContentTypeMetadata;

    /**
     * The content type a class names, as a collection's categoryType or an Entries field's contentType does: the one
     * whose model is the class, or else the first whose model extends or implements it, such as a project's model of a
     * shipped content type, which extends the shipped model
     *
     * @throws UnknownContentTypeException
     */
    public function getByClass(string $class): ContentTypeMetadata;

    /**
     * Every content type, the disabled ones included
     *
     * @return list<ContentTypeMetadata>
     */
    public function all(): array;

    /**
     * The content types that aren't switched off, which editors and shoppers see
     *
     * @return list<ContentTypeMetadata>
     */
    public function enabled(): array;

    /**
     * The codes of the content types switched off in setono_sylius_cms.disabled.content_types, whose entries the admin
     * leaves out
     *
     * @return list<string>
     */
    public function disabledCodes(): array;
}

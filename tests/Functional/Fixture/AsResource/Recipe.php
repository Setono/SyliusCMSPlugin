<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture\AsResource;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Sylius\Resource\Metadata\AsResource;

/**
 * A project's content type without translatable fields of its own, registered with #[AsResource] rather than under
 * sylius_resource.resources (AsResourceContentTypeKernel). It has no field of its own, so no column: once loaded,
 * Doctrine maps it in every kernel of the PHPUnit process, whose database has no column for it
 */
#[ORM\Entity]
#[AsResource(alias: 'app.recipe')]
#[ContentType(code: 'recipe', label: 'app.cms.content_type.recipe')]
class Recipe extends Entry
{
    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}

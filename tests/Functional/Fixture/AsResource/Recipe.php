<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture\AsResource;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Sylius\Resource\Metadata\AsResource;

/**
 * A project's content type without translatable fields of its own, registered with #[AsResource] rather than under
 * sylius_resource.resources (AsResourceContentTypeKernel). Its column isn't in the test database's schema, so tests
 * only read its mapping
 */
#[ORM\Entity]
#[AsResource(alias: 'app.recipe')]
#[ContentType(code: 'recipe', label: 'app.cms.content_type.recipe')]
class Recipe extends Entry
{
    #[ORM\Column(type: 'string', nullable: true)]
    #[Text(label: 'app.cms.field.servings')]
    private ?string $servings = null;

    public function getServings(): ?string
    {
        return $this->servings;
    }

    protected function createTranslation(): EntryTranslationInterface
    {
        return new EntryTranslation();
    }
}

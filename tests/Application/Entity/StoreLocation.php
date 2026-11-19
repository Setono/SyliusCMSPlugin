<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Field\Snippet;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * The test application's store locations, as a project adds a content type of its own: a URL under the prefix
 * "stores", no block tree, a required shared field, the city, and a translatable one on its translations, the
 * directions. Sources list them by city. The plugin prefixes their columns, so the city is store_location_city
 */
#[ORM\Entity]
#[ContentType(
    code: 'store_location',
    label: 'app.cms.content_type.store_location',
    prefix: 'stores',
    order: ['city' => 'ASC'],
)]
class StoreLocation extends Entry
{
    #[ORM\Column(type: 'string', nullable: true)]
    #[Text(label: 'app.cms.field.city', required: true)]
    private ?string $city = null;

    /**
     * A snippet the store's page shows, such as its opening hours this week, in a column of its own: a Snippet field
     * that isn't in a block
     */
    #[ORM\Column(type: 'string', nullable: true)]
    #[Snippet(label: 'app.cms.field.store_banner')]
    private ?string $banner = null;

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function setBanner(?string $banner): void
    {
        $this->banner = $banner;
    }

    protected function createTranslation(): EntryTranslationInterface
    {
        return new StoreLocationTranslation();
    }
}

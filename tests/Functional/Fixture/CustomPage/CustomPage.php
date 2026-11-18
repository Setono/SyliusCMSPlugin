<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture\CustomPage;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A project's subclass of the shipped Page model, which CustomPageKernel overrides the page resource with, as a project
 * extends a shipped content type. It keeps Page's #[ContentType], so its entries are still pages
 */
#[ORM\Entity]
class CustomPage extends Page
{
}

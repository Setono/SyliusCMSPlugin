<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture;

use Setono\SyliusCMSPlugin\Field\Taxon;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Sylius\Component\Core\Model\TaxonInterface;

final class OnSaleParams
{
    #[Taxon(label: 'app.source.on_sale.taxon', required: true)]
    public ?TaxonInterface $taxon = null;

    #[Toggle(label: 'app.source.on_sale.only_new')]
    public bool $onlyNew = false;
}

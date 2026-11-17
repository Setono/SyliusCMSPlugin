<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * Matches when the page's taxon, such as the taxon page's, is one of the taxons. A taxon below one of them is a page of
 * its own, so it doesn't match. A deleted taxon, or one the shopper may not see, matches nothing. See "Condition kinds"
 * in docs/spec/targeting.md
 */
#[AsConditionKind(code: self::CODE, label: 'setono_sylius_cms.condition_kind.taxon', needs: ['taxon'], reads: [Reads::HostPage], params: TaxonParams::class)]
final class TaxonKind implements ConditionKindInterface
{
    public const CODE = 'taxon';

    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof TaxonParams) {
            throw new \InvalidArgumentException(sprintf('The taxon condition takes %s, got %s', TaxonParams::class, $params::class));
        }

        $taxon = $context->get('taxon');
        if (!$taxon instanceof TaxonInterface || null === $params->taxons) {
            return false;
        }

        foreach ($params->taxons as $picked) {
            if ($picked->getCode() === $taxon->getCode()) {
                return true;
            }
        }

        return false;
    }
}

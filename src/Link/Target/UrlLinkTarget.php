<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Link\Target;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Link\RefCheckingLinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A URL as typed, per locale: on another site (http or https), an email address (mailto:), a phone number (tel:), a
 * path on the shop, or an anchor on the page. Any other scheme, such as javascript:, leads nowhere, and publish refuses
 * it. Its name, which an empty label shows, is the URL, or the address or the number. See "Link targets" in
 * docs/spec/extending.md
 */
#[AsLinkTarget(code: self::CODE, label: 'setono_sylius_cms.link_target.url', translatableRef: true)]
final class UrlLinkTarget implements RefCheckingLinkTargetInterface
{
    public const CODE = 'url';

    /** A URL a link can lead to, with no whitespace in it */
    private const ALLOWED = '~^(?:https?://|mailto:|tel:|/|#|\?)\S*$~i';

    public function resolve(string $ref, RenderContext $context): ?ResolvedLink
    {
        if (null !== $this->refError($ref)) {
            return null;
        }

        return new ResolvedLink('url', $ref, $ref, (string) preg_replace('~^(?:mailto|tel):~i', '', $ref));
    }

    public function search(string $query, string $localeCode): iterable
    {
        return [];
    }

    public function refError(string $ref): ?string
    {
        return 1 === preg_match(self::ALLOWED, $ref) ? null : 'setono_sylius_cms.field.not_link_url';
    }
}

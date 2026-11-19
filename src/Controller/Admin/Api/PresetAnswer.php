<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An editor preset as the endpoints that save it answer with it: its id, its name, category and description in the
 * admin user's locale, and when it last changed, in UTC
 *
 * @internal
 */
final class PresetAnswer
{
    private function __construct()
    {
    }

    /**
     * @return array{id: string, name: string, category: string|null, description: string|null, changedAt: string|null}
     */
    public static function of(Preset $preset, TranslatorInterface $translator): array
    {
        $text = static fn (string|TranslationKey|null $text): ?string => $text instanceof TranslationKey ? $translator->trans($text->key, [], $text->domain) : $text;

        return [
            'id' => $preset->id(),
            'name' => (string) $text($preset->name),
            'category' => $text($preset->category),
            'description' => $text($preset->description),
            'changedAt' => $preset->changedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}

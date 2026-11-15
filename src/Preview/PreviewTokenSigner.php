<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Psr\Clock\ClockInterface;

/**
 * Signs and verifies the short-lived tokens that turn preview mode on. A token is the preview as base64url JSON,
 * a dot, and an HMAC-SHA256 of it with the app's secret. Its format is internal: only this class reads it
 *
 * @internal
 */
final readonly class PreviewTokenSigner
{
    /**
     * Seconds a token stays valid. The builder asks for a new token every time it reloads the preview
     */
    public const LIFETIME = 3600;

    private const CONTEXT = 'setono_sylius_cms_preview.';

    public function __construct(
        #[\SensitiveParameter]
        private string $secret,
        private ClockInterface $clock,
    ) {
    }

    public function sign(Preview $preview): string
    {
        $payload = self::encode(json_encode([
            'entry' => $preview->entryUuid,
            'channel' => $preview->channelCode,
            'locale' => $preview->localeCode,
            'expires' => $this->clock->now()->getTimestamp() + self::LIFETIME,
        ], \JSON_THROW_ON_ERROR));

        return $payload . '.' . $this->signature($payload);
    }

    /**
     * The preview a token asks for, or null when it's malformed, tampered with or expired
     */
    public function verify(string $token): ?Preview
    {
        $parts = explode('.', $token);
        if (2 !== count($parts) || !hash_equals($this->signature($parts[0]), $parts[1])) {
            return null;
        }

        $data = json_decode(self::decode($parts[0]), true);
        if (!is_array($data) || !is_string($data['entry'] ?? null) || !is_string($data['channel'] ?? null) || !is_string($data['locale'] ?? null) || !is_int($data['expires'] ?? null)) {
            return null;
        }

        if ($this->clock->now()->getTimestamp() >= $data['expires']) {
            return null;
        }

        return new Preview($data['entry'], $data['channel'], $data['locale']);
    }

    private function signature(string $payload): string
    {
        return self::encode(hash_hmac('sha256', self::CONTEXT . $payload, $this->secret, true));
    }

    private static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function decode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}

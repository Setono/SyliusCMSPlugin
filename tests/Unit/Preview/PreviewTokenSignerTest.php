<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Symfony\Component\Clock\MockClock;

final class PreviewTokenSignerTest extends TestCase
{
    private const ENTRY = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 10:00:00 UTC');
    }

    /**
     * @test
     */
    public function it_verifies_a_token_it_signed(): void
    {
        $signer = $this->signer();

        $preview = $signer->verify($signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US')));

        self::assertEquals(new Preview(self::ENTRY, 'WEB', 'en_US'), $preview);
    }

    /**
     * @test
     */
    public function it_signs_tokens_that_fit_in_a_query_string_unencoded(): void
    {
        $token = $this->signer()->sign(new Preview(self::ENTRY, 'WEB', 'en_US'));

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token);
        self::assertSame($token, rawurlencode($token));
    }

    /**
     * @test
     */
    public function it_signs_a_token_valid_for_an_hour(): void
    {
        $signer = $this->signer();
        $token = $signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US'));

        $this->clock->sleep(3599);
        self::assertNotNull($signer->verify($token));

        $this->clock->sleep(1);
        self::assertNull($signer->verify($token), 'A token expires an hour after it was signed');
    }

    /**
     * @test
     */
    public function it_refuses_a_token_whose_content_was_changed(): void
    {
        $signer = $this->signer();
        $token = $signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US'));
        [, $signature] = explode('.', $token);

        $tampered = self::encode(sprintf('{"entry":"%s","channel":"OTHER","locale":"en_US","expires":%d}', self::ENTRY, $this->clock->now()->getTimestamp() + 3600));

        self::assertNull($signer->verify($tampered . '.' . $signature));
    }

    /**
     * @test
     */
    public function it_refuses_a_token_whose_signature_was_changed(): void
    {
        $signer = $this->signer();
        $token = $signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US'));
        $tampered = substr($token, 0, -1) . (str_ends_with($token, 'A') ? 'B' : 'A');

        self::assertNull($signer->verify($tampered));
    }

    /**
     * @test
     */
    public function it_refuses_a_token_signed_with_another_secret(): void
    {
        $token = $this->signer('another secret')->sign(new Preview(self::ENTRY, 'WEB', 'en_US'));

        self::assertNull($this->signer()->verify($token));
    }

    /**
     * @test
     *
     * @dataProvider malformedTokens
     */
    public function it_refuses_a_malformed_token(string $token): void
    {
        self::assertNull($this->signer()->verify($token));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'no signature' => ['eyJlbnRyeSI6ImEifQ'];
        yield 'three parts' => ['a.b.c'];
        yield 'not base64' => ['!!!.!!!'];
    }

    /**
     * @test
     *
     * @dataProvider signedPayloadsOfTheWrongShape
     */
    public function it_refuses_a_signed_payload_of_the_wrong_shape(string $payload): void
    {
        $encoded = self::encode($payload);
        $signature = self::encode(hash_hmac('sha256', 'setono_sylius_cms_preview.' . $encoded, 'secret', true));

        self::assertNull($this->signer()->verify($encoded . '.' . $signature));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function signedPayloadsOfTheWrongShape(): iterable
    {
        $expires = (new \DateTimeImmutable('2026-11-14 11:00:00 UTC'))->getTimestamp();

        yield 'not JSON' => ['{'];
        yield 'not an object' => ['"entry"'];
        yield 'no entry' => [sprintf('{"channel":"WEB","locale":"en_US","expires":%d}', $expires)];
        yield 'entry not a string' => [sprintf('{"entry":1,"channel":"WEB","locale":"en_US","expires":%d}', $expires)];
        yield 'channel not a string' => [sprintf('{"entry":"%s","channel":null,"locale":"en_US","expires":%d}', self::ENTRY, $expires)];
        yield 'locale not a string' => [sprintf('{"entry":"%s","channel":"WEB","locale":[],"expires":%d}', self::ENTRY, $expires)];
        yield 'expires not a number' => [sprintf('{"entry":"%s","channel":"WEB","locale":"en_US","expires":"%d"}', self::ENTRY, $expires)];
    }

    /**
     * @test
     */
    public function it_refuses_a_signed_payload_that_is_not_base64(): void
    {
        $signature = self::encode(hash_hmac('sha256', 'setono_sylius_cms_preview.!!!', 'secret', true));

        self::assertNull($this->signer()->verify('!!!.' . $signature));
    }

    /**
     * @test
     */
    public function it_reads_a_signed_payload_of_the_right_shape(): void
    {
        $expires = $this->clock->now()->getTimestamp() + 60;
        $encoded = self::encode(sprintf('{"entry":"%s","channel":"WEB","locale":"da_DK","expires":%d}', self::ENTRY, $expires));
        $signature = self::encode(hash_hmac('sha256', 'setono_sylius_cms_preview.' . $encoded, 'secret', true));

        self::assertEquals(new Preview(self::ENTRY, 'WEB', 'da_DK'), $this->signer()->verify($encoded . '.' . $signature));
    }

    private function signer(string $secret = 'secret'): PreviewTokenSigner
    {
        return new PreviewTokenSigner($secret, $this->clock);
    }

    private static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

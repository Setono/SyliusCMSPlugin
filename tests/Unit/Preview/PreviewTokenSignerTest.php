<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Preview\SampleObject;
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
    public function it_verifies_a_token_of_a_revisions_read_only_preview_it_signed(): void
    {
        $signer = $this->signer();

        $preview = $signer->verify($signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US', 42)));

        self::assertEquals(new Preview(self::ENTRY, 'WEB', 'en_US', 42), $preview);
        self::assertTrue($preview?->isReadOnly());
        self::assertFalse((new Preview(self::ENTRY, 'WEB', 'en_US'))->isReadOnly());
    }

    /**
     * The token carries Shown to and Date. See "Shown to and Date" in docs/spec/targeting.md
     *
     * @test
     */
    public function it_verifies_a_token_with_shown_to_and_a_date_it_signed(): void
    {
        $signer = $this->signer();
        $date = new \DateTimeImmutable('2026-12-01T09:30:00Z');

        $preview = $signer->verify($signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US', shownTo: 'wholesale', date: $date)));

        self::assertNotNull($preview);
        self::assertSame('wholesale', $preview->shownTo);
        self::assertEquals($date, $preview->date);
        self::assertSame('2026-12-01T09:30:00+00:00', $preview->date?->format(\DATE_ATOM));
    }

    /**
     * A snippet's preview shows it in one of its placements, on the page of a sample object. See "Preview mode" in
     * docs/spec/builder.md
     *
     * @test
     */
    public function it_verifies_a_token_with_a_placement_and_a_sample_object_it_signed(): void
    {
        $signer = $this->signer();

        $preview = $signer->verify($signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US', placementUuid: '0193a5be-0000-7000-8000-0000000000c1', sample: new SampleObject('product', 'T_SHIRT'))));

        self::assertNotNull($preview);
        self::assertSame('0193a5be-0000-7000-8000-0000000000c1', $preview->placementUuid);
        self::assertEquals(new SampleObject('product', 'T_SHIRT'), $preview->sample);
    }

    /**
     * @test
     */
    public function it_verifies_a_token_without_a_placement_or_a_sample_object(): void
    {
        $signer = $this->signer();

        $preview = $signer->verify($signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US')));

        self::assertNull($preview?->placementUuid);
        self::assertNull($preview?->sample);
    }

    /**
     * @test
     */
    public function it_verifies_a_token_without_shown_to_or_a_date_as_the_shopper_now(): void
    {
        $signer = $this->signer();

        $preview = $signer->verify($signer->sign(new Preview(self::ENTRY, 'WEB', 'en_US')));

        self::assertNull($preview?->shownTo);
        self::assertNull($preview?->date);
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
        yield 'revision not a number' => [sprintf('{"entry":"%s","channel":"WEB","locale":"en_US","revision":"42","expires":%d}', self::ENTRY, $expires)];
        yield 'shown to not a string' => [sprintf('{"entry":"%s","channel":"WEB","locale":"en_US","shownTo":1,"expires":%d}', self::ENTRY, $expires)];
        yield 'date not a number' => [sprintf('{"entry":"%s","channel":"WEB","locale":"en_US","date":"2026-12-01T09:30:00Z","expires":%d}', self::ENTRY, $expires)];
        yield 'placement not a string' => [sprintf('{"entry":"%s","channel":"WEB","locale":"en_US","placement":1,"expires":%d}', self::ENTRY, $expires)];
        yield 'sample not a pair' => [sprintf('{"entry":"%s","channel":"WEB","locale":"en_US","sample":"T_SHIRT","expires":%d}', self::ENTRY, $expires)];
        yield 'sample without a ref' => [sprintf('{"entry":"%s","channel":"WEB","locale":"en_US","sample":["product"],"expires":%d}', self::ENTRY, $expires)];
        yield 'sample ref not a string' => [sprintf('{"entry":"%s","channel":"WEB","locale":"en_US","sample":["product",1],"expires":%d}', self::ENTRY, $expires)];
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

    /**
     * @test
     */
    public function it_reads_a_signed_payload_of_a_revisions_preview(): void
    {
        $expires = $this->clock->now()->getTimestamp() + 60;
        $encoded = self::encode(sprintf('{"entry":"%s","channel":"WEB","locale":"da_DK","revision":42,"expires":%d}', self::ENTRY, $expires));
        $signature = self::encode(hash_hmac('sha256', 'setono_sylius_cms_preview.' . $encoded, 'secret', true));

        self::assertEquals(new Preview(self::ENTRY, 'WEB', 'da_DK', 42), $this->signer()->verify($encoded . '.' . $signature));
    }

    /**
     * @test
     */
    public function it_reads_a_signed_payload_with_shown_to_and_a_date(): void
    {
        $expires = $this->clock->now()->getTimestamp() + 60;
        $date = (new \DateTimeImmutable('2026-12-01T09:30:00Z'))->getTimestamp();
        $encoded = self::encode(sprintf('{"entry":"%s","channel":"WEB","locale":"da_DK","shownTo":"@no_group","date":%d,"expires":%d}', self::ENTRY, $date, $expires));
        $signature = self::encode(hash_hmac('sha256', 'setono_sylius_cms_preview.' . $encoded, 'secret', true));

        self::assertEquals(
            new Preview(self::ENTRY, 'WEB', 'da_DK', shownTo: '@no_group', date: new \DateTimeImmutable('2026-12-01T09:30:00Z')),
            $this->signer()->verify($encoded . '.' . $signature),
        );
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

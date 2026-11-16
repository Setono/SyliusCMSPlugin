<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;

final class FingerprintTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_the_lowercase_hex_sha_256_of_the_value_in_canonical_json(): void
    {
        self::assertSame(hash('sha256', '"Winter sale"'), Fingerprint::of('Winter sale'));
        self::assertSame(hash('sha256', '{"content":[],"type":"doc"}'), Fingerprint::of(['type' => 'doc', 'content' => []]));
    }

    /**
     * @test
     */
    public function it_does_not_change_with_the_order_of_an_object_s_keys(): void
    {
        $document = ['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Hi']]]]];
        $reordered = ['content' => [['content' => [['text' => 'Hi', 'type' => 'text']], 'attrs' => ['level' => 2], 'type' => 'heading']], 'type' => 'doc'];

        self::assertSame(Fingerprint::of($document), Fingerprint::of($reordered));
    }

    /**
     * @test
     */
    public function it_changes_with_the_value(): void
    {
        self::assertNotSame(Fingerprint::of('Winter sale'), Fingerprint::of('Winter sale!'));
        self::assertNotSame(Fingerprint::of(['b', 'a']), Fingerprint::of(['a', 'b']));
    }

    /**
     * The builder computes fingerprints too, and its tests check the same fixture, so both give a value the same one
     *
     * @test
     *
     * @dataProvider provideSharedCases
     */
    public function it_gives_the_values_of_the_shared_fixture_the_fingerprints_the_builder_gives_them(mixed $value, string $fingerprint): void
    {
        self::assertSame($fingerprint, Fingerprint::of($value));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public function provideSharedCases(): iterable
    {
        $cases = CanonicalJson::decode((string) file_get_contents(dirname(__DIR__, 2) . '/Fixture/fingerprints.json'));
        self::assertIsArray($cases);

        foreach ($cases as $case) {
            self::assertIsArray($case);
            self::assertIsString($case['case']);
            self::assertIsString($case['fingerprint']);

            yield $case['case'] => [$case['value'], $case['fingerprint']];
        }
    }

    /**
     * @test
     */
    public function it_tells_a_fingerprint_from_another_string(): void
    {
        self::assertTrue(Fingerprint::isValid(Fingerprint::of('Winter sale')));
        self::assertFalse(Fingerprint::isValid(strtoupper(Fingerprint::of('Winter sale'))));
        self::assertFalse(Fingerprint::isValid(substr(Fingerprint::of('Winter sale'), 1)));
        self::assertFalse(Fingerprint::isValid(Fingerprint::of('Winter sale') . '0'));
        self::assertFalse(Fingerprint::isValid(Fingerprint::of('Winter sale') . "\n"));
        self::assertFalse(Fingerprint::isValid('x' . substr(Fingerprint::of('Winter sale'), 1)));
        self::assertFalse(Fingerprint::isValid(''));
    }
}

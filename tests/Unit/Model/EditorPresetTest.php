<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\EditorPreset;
use Symfony\Component\Uid\Uuid;

final class EditorPresetTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_created_and_changed_at_the_same_moment_in_utc(): void
    {
        $before = new \DateTimeImmutable('-1 second');
        $preset = new EditorPreset();

        self::assertNull($preset->getId());
        self::assertTrue(Uuid::isValid($preset->getUuid()));
        self::assertSame('', $preset->getContent());
        self::assertSame('UTC', $preset->getCreatedAt()->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $preset->getCreatedAt());
        self::assertEquals($preset->getCreatedAt(), $preset->getChangedAt());
    }

    /**
     * @test
     */
    public function it_keeps_its_uuid_lowercase_and_when_it_changed_in_utc_while_its_creation_stays(): void
    {
        $preset = new EditorPreset();
        $createdAt = $preset->getCreatedAt();

        $preset->setUuid('0193A5BE-7400-7D3E-8F4A-5B6C7D8E9F0A');
        $preset->setContent('{"format":1}');
        $preset->setChangedAt(new \DateTimeImmutable('2026-11-20 09:30:00', new \DateTimeZone('Europe/Copenhagen')));

        self::assertSame('0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a', $preset->getUuid());
        self::assertSame('{"format":1}', $preset->getContent());
        self::assertSame('2026-11-20T08:30:00+00:00', $preset->getChangedAt()->format(\DATE_ATOM));
        self::assertEquals($createdAt, $preset->getCreatedAt());
    }
}

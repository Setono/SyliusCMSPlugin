<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Json;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;

final class CanonicalJsonTest extends TestCase
{
    /**
     * @test
     */
    public function it_keeps_the_listed_order_of_a_listed_object(): void
    {
        $json = CanonicalJson::encode(new ListedObject(['key' => 'a', 'type' => 'heading', 'version' => 1]));

        self::assertSame('{"key":"a","type":"heading","version":1}', $json);
    }

    /**
     * @test
     */
    public function it_sorts_the_keys_of_every_other_object_by_byte_value(): void
    {
        $json = CanonicalJson::encode(['text' => 1, 'B' => 2, 'a' => 3, 'level' => 4, '_x' => 5]);

        self::assertSame('{"B":2,"_x":5,"a":3,"level":4,"text":1}', $json);
    }

    /**
     * @test
     */
    public function it_sorts_maps_nested_inside_listed_objects_and_lists(): void
    {
        $json = CanonicalJson::encode(new ListedObject([
            'fields' => ['text' => ['en_US' => 'Hello', 'da_DK' => 'Hej'], 'level' => 'h2'],
            'blocks' => [['z' => 1, 'a' => 2]],
        ]));

        self::assertSame('{"fields":{"level":"h2","text":{"da_DK":"Hej","en_US":"Hello"}},"blocks":[{"a":2,"z":1}]}', $json);
    }

    /**
     * @test
     */
    public function it_writes_an_empty_map_as_an_object_and_an_empty_list_as_an_array(): void
    {
        $json = CanonicalJson::encode(new ListedObject(['fields' => new \stdClass(), 'children' => [], 'empty' => new ListedObject([])]));

        self::assertSame('{"fields":{},"children":[],"empty":{}}', $json);
    }

    /**
     * @test
     */
    public function it_sorts_the_keys_of_a_std_class(): void
    {
        $object = new \stdClass();
        $object->b = 1;
        $object->a = 2;

        self::assertSame('{"a":2,"b":1}', CanonicalJson::encode($object));
    }

    /**
     * @test
     */
    public function it_writes_a_map_with_numeric_keys_as_an_object(): void
    {
        self::assertSame('{"1":"b","2":"a"}', CanonicalJson::encode([2 => 'a', 1 => 'b']));
    }

    /**
     * @test
     */
    public function it_leaves_slashes_and_unicode_unescaped_and_keeps_zero_fractions(): void
    {
        $json = CanonicalJson::encode(['url' => 'https://example.com/a', 'text' => 'Rødgrød med fløde', 'number' => 1.0]);

        self::assertSame('{"number":1.0,"text":"Rødgrød med fløde","url":"https://example.com/a"}', $json);
    }

    /**
     * @test
     */
    public function it_writes_scalars_and_null_as_they_are(): void
    {
        self::assertSame('[null,true,false,1,"a"]', CanonicalJson::encode([null, true, false, 1, 'a']));
    }

    /**
     * @test
     */
    public function it_pretty_prints_files_with_four_spaces_and_a_final_newline(): void
    {
        $json = CanonicalJson::encodeFile(new ListedObject(['layout' => null, 'blocks' => [], 'fields' => new \stdClass()]));

        self::assertSame("{\n    \"layout\": null,\n    \"blocks\": [],\n    \"fields\": {}\n}\n", $json);
    }

    /**
     * @test
     */
    public function it_throws_on_values_json_cannot_hold(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CanonicalJson::encode(['date' => new \DateTimeImmutable()]);
    }

    /**
     * @test
     */
    public function it_throws_on_invalid_utf8(): void
    {
        $this->expectException(\JsonException::class);

        CanonicalJson::encode("\xB1\x31");
    }

    /**
     * @test
     */
    public function it_decodes_objects_to_arrays_and_keeps_empty_objects_as_empty_maps(): void
    {
        $decoded = CanonicalJson::decode('{"fields":{},"children":[],"text":{"en_US":"Hello"},"list":[{"a":1}]}');

        self::assertIsArray($decoded);
        self::assertEquals(new \stdClass(), $decoded['fields']);
        self::assertSame([], $decoded['children']);
        self::assertSame(['en_US' => 'Hello'], $decoded['text']);
        self::assertSame([['a' => 1]], $decoded['list']);
    }

    /**
     * @test
     */
    public function it_round_trips_canonical_json(): void
    {
        $json = '{"a":{},"b":[],"c":{"d":[1,2.5,{"e":null}]}}';

        self::assertSame($json, CanonicalJson::encode(CanonicalJson::decode($json)));
    }

    /**
     * @test
     */
    public function it_throws_on_invalid_json(): void
    {
        $this->expectException(\JsonException::class);

        CanonicalJson::decode('{');
    }
}

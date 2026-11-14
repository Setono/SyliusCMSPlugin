<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Tree;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

final class StoredTreeSerializerTest extends TestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const CHILD_KEY = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    /**
     * @test
     */
    public function it_writes_an_empty_tree_with_every_key_present(): void
    {
        $json = (new StoredTreeSerializer())->toJson(new StoredTree(new StoredLayout('standard', 1), []));

        self::assertSame('{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[]}', $json);
    }

    /**
     * @test
     */
    public function it_writes_a_null_layout_for_a_content_type_without_its_own_url(): void
    {
        self::assertSame('{"layout":null,"blocks":[]}', (new StoredTreeSerializer())->toJson(new StoredTree(null, [])));
    }

    /**
     * @test
     */
    public function it_writes_a_block_with_its_keys_in_listed_order_and_its_field_values_sorted(): void
    {
        $block = new StoredBlock(self::KEY, 'heading', 1);
        $block->fields = ['text' => ['en_US' => 'Hello', 'da_DK' => 'Hej'], 'level' => 'h3'];

        $json = (new StoredTreeSerializer())->toJson(new StoredTree(null, [$block]));

        self::assertSame(
            '{"layout":null,"blocks":[{"key":"' . self::KEY . '","type":"heading","version":1,"style":null,"fields":{"level":"h3","text":{"da_DK":"Hej","en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}',
            $json,
        );
    }

    /**
     * @test
     */
    public function it_writes_a_block_without_values_with_empty_maps_and_lists(): void
    {
        $json = (new StoredTreeSerializer())->toJson(new StoredTree(null, [new StoredBlock(self::KEY, 'heading', 1)]));

        self::assertSame('{"layout":null,"blocks":[{"key":"' . self::KEY . '","type":"heading","version":1,"style":null,"fields":{},"translated":{},"conditions":[],"children":[]}]}', $json);
    }

    /**
     * @test
     */
    public function it_writes_a_condition_without_negate_as_not_negated(): void
    {
        $block = new StoredBlock(self::KEY, 'banner', 1);
        $block->conditions = [['kind' => 'guest']];

        $json = (new StoredTreeSerializer())->toJson(new StoredTree(null, [$block]));

        self::assertStringContainsString('"conditions":[{"kind":"guest","negate":false,"params":{}}]', $json);
    }

    /**
     * @test
     */
    public function it_reads_every_condition_and_child(): void
    {
        $tree = (new StoredTreeSerializer())->fromJson('{"layout":null,"blocks":[{"key":"' . self::KEY . '","type":"columns","version":1,"style":null,"fields":{},"translated":{},"conditions":[{"kind":"a","negate":false,"params":{}},{"kind":"b","negate":true,"params":{}}],"children":[{"key":"' . self::CHILD_KEY . '","type":"column","version":1},{"key":"0193a5be-70aa-7c11-8e2d-1b2c3d4e5f61","type":"column","version":1}]}]}');

        $conditions = $tree->blocks[0]->conditions;
        self::assertCount(2, $conditions);
        self::assertSame('a', $conditions[0]['kind'] ?? null);
        self::assertSame('b', $conditions[1]['kind'] ?? null);
        self::assertCount(2, $tree->blocks[0]->children);
    }

    /**
     * @test
     */
    public function it_reads_a_block_whose_maps_are_objects(): void
    {
        $fields = new \stdClass();
        $fields->text = 'Hello';
        $fields->level = 'h3';

        $block = (new StoredTreeSerializer())->denormalizeBlock(['key' => self::KEY, 'type' => 'heading', 'version' => 1, 'fields' => $fields]);

        self::assertSame(['text' => 'Hello', 'level' => 'h3'], $block->fields);
    }

    /**
     * @test
     */
    public function it_writes_translation_state_and_conditions_in_listed_order(): void
    {
        $block = new StoredBlock(self::KEY, 'banner', 2, 'dark');
        $block->translated = ['heading' => ['da_DK' => ['fingerprint' => 'abc', 'from' => 'en_US']]];
        $block->conditions = [['params' => ['groups' => ['wholesale']], 'negate' => true, 'kind' => 'customer_group']];

        $json = (new StoredTreeSerializer())->toJson(new StoredTree(null, [$block]));

        self::assertStringContainsString('"style":"dark"', $json);
        self::assertStringContainsString('"translated":{"heading":{"da_DK":{"from":"en_US","fingerprint":"abc"}}}', $json);
        self::assertStringContainsString('"conditions":[{"kind":"customer_group","negate":true,"params":{"groups":["wholesale"]}}]', $json);
    }

    /**
     * @test
     */
    public function it_writes_empty_condition_params_as_an_object(): void
    {
        $block = new StoredBlock(self::KEY, 'banner', 1);
        $block->conditions = [['kind' => 'guest', 'negate' => false, 'params' => []]];

        $json = (new StoredTreeSerializer())->toJson(new StoredTree(null, [$block]));

        self::assertStringContainsString('"conditions":[{"kind":"guest","negate":false,"params":{}}]', $json);
    }

    /**
     * @test
     */
    public function it_writes_children_in_order(): void
    {
        $parent = new StoredBlock(self::KEY, 'columns', 1);
        $parent->children = [new StoredBlock(self::CHILD_KEY, 'column', 1)];

        $json = (new StoredTreeSerializer())->toJson(new StoredTree(null, [$parent]));

        self::assertStringContainsString('"children":[{"key":"' . self::CHILD_KEY . '","type":"column"', $json);
    }

    /**
     * @test
     */
    public function it_reads_what_it_writes(): void
    {
        $serializer = new StoredTreeSerializer();
        $json = '{"layout":{"code":"standard","version":1,"fields":{"showTitle":false},"translated":{}},"blocks":[{"key":"' . self::KEY . '","type":"heading","version":1,"style":null,"fields":{"level":"h3","text":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[{"key":"' . self::CHILD_KEY . '","type":"text","version":3,"style":"muted","fields":{"empty":{}},"translated":{},"conditions":[{"kind":"guest","negate":false,"params":{}}],"children":[]}]}]}';

        $tree = $serializer->fromJson($json);

        self::assertNotNull($tree->layout);
        self::assertSame('standard', $tree->layout->code);
        self::assertSame(['showTitle' => false], $tree->layout->fields);
        self::assertCount(1, $tree->blocks);
        self::assertSame(self::KEY, $tree->blocks[0]->key);
        self::assertSame('heading', $tree->blocks[0]->type);
        self::assertSame(['level' => 'h3', 'text' => ['en_US' => 'Hello']], $tree->blocks[0]->fields);
        self::assertSame('muted', $tree->blocks[0]->children[0]->style);
        self::assertSame(3, $tree->blocks[0]->children[0]->version);
        self::assertSame($json, $serializer->toJson($tree));
    }

    /**
     * @test
     */
    public function it_fills_in_missing_optional_block_keys_when_reading(): void
    {
        $block = (new StoredTreeSerializer())->denormalizeBlock(['key' => self::KEY, 'type' => 'heading', 'version' => 1]);

        self::assertNull($block->style);
        self::assertSame([], $block->fields);
        self::assertSame([], $block->translated);
        self::assertSame([], $block->conditions);
        self::assertSame([], $block->children);
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidBlocks
     */
    public function it_rejects_an_invalid_block(mixed $block): void
    {
        $this->expectException(InvalidStoredTreeException::class);

        (new StoredTreeSerializer())->denormalizeBlock($block);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public function provideInvalidBlocks(): iterable
    {
        $valid = ['key' => self::KEY, 'type' => 'heading', 'version' => 1];

        yield 'not an object' => ['heading'];
        yield 'a list' => [[1, 2]];
        yield 'no key' => [['type' => 'heading', 'version' => 1]];
        yield 'a key that is not a lowercase UUID' => [['key' => strtoupper(self::KEY)] + $valid];
        yield 'a key that is no UUID at all' => [['key' => 'abc'] + $valid];
        yield 'no type' => [['key' => self::KEY, 'version' => 1]];
        yield 'an invalid type code' => [['type' => 'Heading'] + $valid];
        yield 'no version' => [['key' => self::KEY, 'type' => 'heading']];
        yield 'a version below 1' => [['version' => 0] + $valid];
        yield 'a string version' => [['version' => '1'] + $valid];
        yield 'a non-string style' => [['style' => 1] + $valid];
        yield 'fields as a list' => [['fields' => ['a']] + $valid];
        yield 'fields as a string' => [['fields' => 'a'] + $valid];
        yield 'translated as a list' => [['translated' => ['a']] + $valid];
        yield 'conditions as a map' => [['conditions' => ['a' => ['kind' => 'guest', 'negate' => false, 'params' => []]]] + $valid];
        yield 'conditions as a string' => [['conditions' => 'guest'] + $valid];
        yield 'a condition that is not an object' => [['conditions' => ['a']] + $valid];
        yield 'children as a map' => [['children' => ['a' => ['key' => self::CHILD_KEY, 'type' => 'text', 'version' => 1]]] + $valid];
        yield 'an invalid child' => [['children' => [['type' => 'text']]] + $valid];
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidTrees
     */
    public function it_rejects_an_invalid_tree(string $json): void
    {
        $this->expectException(InvalidStoredTreeException::class);

        (new StoredTreeSerializer())->fromJson($json);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public function provideInvalidTrees(): iterable
    {
        yield 'invalid JSON' => ['{'];
        yield 'a list' => ['[]'];
        yield 'no blocks' => ['{"layout":null}'];
        yield 'blocks as a map' => ['{"layout":null,"blocks":{"a":1}}'];
        yield 'a layout without a code' => ['{"layout":{"version":1},"blocks":[]}'];
        yield 'a layout that is a string' => ['{"layout":"standard","blocks":[]}'];
        yield 'a layout with a version below 1' => ['{"layout":{"code":"standard","version":0},"blocks":[]}'];
        yield 'a layout with fields as a list' => ['{"layout":{"code":"standard","version":1,"fields":[1]},"blocks":[]}'];
        yield 'a layout with translated as a list' => ['{"layout":{"code":"standard","version":1,"translated":[1]},"blocks":[]}'];
    }

    /**
     * @test
     */
    public function it_reads_a_tree_without_a_layout_key_as_one_without_a_layout(): void
    {
        self::assertNull((new StoredTreeSerializer())->fromJson('{"blocks":[]}')->layout);
    }
}

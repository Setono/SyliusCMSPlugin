<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\Article;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\RenameField;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgrader;
use Setono\SyliusCMSPlugin\Upgrade\UpgraderInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class StoredDataUpgraderTest extends TestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const CHILD_KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function it_chains_a_block_types_upgraders_from_the_stored_version_to_the_codes_and_sets_the_version_after_each(): void
    {
        // Teaser is at version 3: "title" became "headline" in 2, and "headline" became "heading" in 3
        $toTwo = new RenameField('title', 'headline');
        $toThree = new RenameField('headline', 'heading');
        $upgrader = self::upgrader(['block_type:teaser:1' => $toTwo, 'block_type:teaser:2' => $toThree]);

        $upgraded = $upgrader->upgradeBlocks([self::block('teaser', 1, ['title' => ['en_US' => 'Hello']])]);

        self::assertSame(
            '{"layout":null,"blocks":[{"key":"' . self::KEY . '","type":"teaser","version":3,"style":null,"fields":{"heading":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}',
            self::encode($upgraded->data),
        );
        self::assertSame(1, $toTwo->received[0]['version'] ?? null);
        self::assertSame(2, $toThree->received[0]['version'] ?? null, 'The version is set after each step');
        self::assertSame([], $upgraded->newer);
    }

    /**
     * @test
     */
    public function a_block_type_upgrader_gets_the_block_without_its_children_which_are_upgraded_on_their_own(): void
    {
        $toTwo = new RenameField('title', 'headline');
        $upgrader = self::upgrader(['block_type:teaser:1' => $toTwo, 'block_type:teaser:2' => new RenameField('headline', 'heading')]);
        $child = self::block('teaser', 1, ['title' => ['en_US' => 'Child']], key: self::CHILD_KEY);

        $upgraded = $upgrader->upgradeBlocks([self::block('teaser', 2, ['headline' => ['en_US' => 'Parent']], [$child])]);

        self::assertSame(
            '{"layout":null,"blocks":[{"key":"' . self::KEY . '","type":"teaser","version":3,"style":null,"fields":{"heading":{"en_US":"Parent"}},"translated":{},"conditions":[],"children":[{"key":"' . self::CHILD_KEY . '","type":"teaser","version":3,"style":null,"fields":{"heading":{"en_US":"Child"}},"translated":{},"conditions":[],"children":[]}]}]}',
            self::encode($upgraded->data),
        );
        self::assertEquals([[
            'key' => self::CHILD_KEY,
            'type' => 'teaser',
            'version' => 1,
            'style' => null,
            'fields' => ['title' => ['en_US' => 'Child']],
            'translated' => [],
            'conditions' => [],
        ]], $toTwo->received, 'Its maps are arrays, even when they are empty');
    }

    /**
     * @test
     */
    public function a_layout_upgrader_gets_the_trees_layout_array_and_the_trees_blocks_are_upgraded_too(): void
    {
        // Landing is a page layout at version 2: "transparent" became "transparentHeader"
        $layoutUpgrader = new RenameField('transparent', 'transparentHeader');
        $upgrader = self::upgrader([
            'layout:page:landing:1' => $layoutUpgrader,
            'block_type:teaser:1' => new RenameField('title', 'headline'),
            'block_type:teaser:2' => new RenameField('headline', 'heading'),
        ]);

        $upgraded = $upgrader->upgradeTree(self::decode([
            'layout' => ['code' => 'landing', 'version' => 1, 'fields' => ['transparent' => true], 'translated' => new \stdClass()],
            'blocks' => [self::block('teaser', 1, ['title' => ['en_US' => 'Hello']])],
        ]), 'page');

        self::assertSame(
            '{"layout":{"code":"landing","version":2,"fields":{"transparentHeader":true},"translated":{}},"blocks":[{"key":"' . self::KEY . '","type":"teaser","version":3,"style":null,"fields":{"heading":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}',
            (new StoredTreeSerializer())->toJson((new StoredTreeSerializer())->denormalize($upgraded->data)),
        );
        self::assertEquals([['code' => 'landing', 'version' => 1, 'fields' => ['transparent' => true], 'translated' => []]], $layoutUpgrader->received);
    }

    /**
     * @test
     */
    public function a_content_type_upgrader_gets_the_entry_without_its_tree_and_the_tree_is_upgraded_as_one_of_the_entrys_content_type(): void
    {
        // Article is at version 2: its shared field "image" became "featuredImage"
        $contentTypeUpgrader = new RenameField('image', 'featuredImage');
        $upgrader = self::upgrader([
            'content_type:article:1' => $contentTypeUpgrader,
            'layout:article:landing:1' => new RenameField('transparent', 'transparentHeader'),
        ]);
        $entry = [
            'uuid' => '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
            'contentType' => 'article',
            'version' => 1,
            'createdAt' => '2026-11-12T08:30:00Z',
            'fields' => ['image' => '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60'],
            'translations' => ['en_US' => ['shown' => true, 'fields' => ['title' => 'Hello'], 'translated' => new \stdClass()]],
        ];

        $upgraded = $upgrader->upgradeEntry(self::decode($entry + [
            'tree' => ['layout' => ['code' => 'landing', 'version' => 1, 'fields' => ['transparent' => true], 'translated' => new \stdClass()], 'blocks' => []],
        ]));

        self::assertSame(
            '{"contentType":"article","createdAt":"2026-11-12T08:30:00Z","fields":{"featuredImage":"0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60"},"translations":{"en_US":{"fields":{"title":"Hello"},"shown":true,"translated":{}}},"tree":{"blocks":[],"layout":{"code":"landing","fields":{"transparentHeader":true},"translated":{},"version":2}},"uuid":"0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e","version":2}',
            CanonicalJson::encode($upgraded->data),
        );
        $entry['translations']['en_US']['translated'] = [];
        self::assertEquals([$entry], $contentTypeUpgrader->received, 'Its maps are arrays, the translations\' too');
    }

    /**
     * @test
     */
    public function it_tells_what_was_saved_with_a_newer_version_than_the_codes_and_keeps_it_as_it_is(): void
    {
        $upgrader = self::upgrader(['block_type:teaser:3' => new RenameField('heading', 'title')]);
        $newerBlock = self::block('teaser', 4, ['title' => ['en_US' => 'From the future']], key: self::CHILD_KEY);

        $upgraded = $upgrader->upgradeEntry(self::decode([
            'uuid' => '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e',
            'contentType' => 'article',
            'version' => 5,
            'fields' => ['image' => 'kept'],
            'tree' => [
                'layout' => ['code' => 'landing', 'version' => 3, 'fields' => ['transparent' => true], 'translated' => new \stdClass()],
                'blocks' => [self::block('teaser', 3, ['heading' => ['en_US' => 'Current']], [$newerBlock])],
            ],
        ]));

        self::assertEquals([
            new NewerVersion(Kind::ContentType, 'article', 5, 2),
            new NewerVersion(Kind::Layout, 'landing', 3, 2),
            new NewerVersion(Kind::BlockType, 'teaser', 4, 3),
        ], $upgraded->newer);
        self::assertSame(
            '{"contentType":"article","fields":{"image":"kept"},"tree":{"blocks":[{"children":[{"children":[],"conditions":[],"fields":{"title":{"en_US":"From the future"}},"key":"' . self::CHILD_KEY . '","style":null,"translated":{},"type":"teaser","version":4}],"conditions":[],"fields":{"heading":{"en_US":"Current"}},"key":"' . self::KEY . '","style":null,"translated":{},"type":"teaser","version":3}],"layout":{"code":"landing","fields":{"transparent":true},"translated":{},"version":3}},"uuid":"0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e","version":5}',
            CanonicalJson::encode($upgraded->data),
        );
        self::assertSame('The block type "teaser" was saved with version 4, but the code has version 3', (string) $upgraded->newer[2]);
    }

    /**
     * @test
     */
    public function it_tells_what_is_newer_in_a_tree_and_in_blocks_too(): void
    {
        $upgrader = self::upgrader();

        self::assertEquals(
            [new NewerVersion(Kind::Layout, 'landing', 3, 2), new NewerVersion(Kind::BlockType, 'teaser', 4, 3)],
            $upgrader->upgradeTree(self::decode(['layout' => ['code' => 'landing', 'version' => 3], 'blocks' => [self::block('teaser', 4)]]), 'page')->newer,
        );
        self::assertEquals(
            [new NewerVersion(Kind::BlockType, 'teaser', 5, 3), new NewerVersion(Kind::BlockType, 'teaser', 4, 3)],
            $upgrader->upgradeBlocks([self::block('teaser', 5), self::block('teaser', 3), self::block('teaser', 4)])->newer,
        );
        self::assertSame([], $upgrader->upgradeBlocks([self::block('teaser', 1), self::block('teaser', 3)])->newer);
    }

    /**
     * @test
     */
    public function a_removed_fields_value_and_translation_state_are_dropped_from_blocks_and_layouts_of_known_types(): void
    {
        $state = ['da_DK' => ['from' => 'en_US', 'fingerprint' => 'abc']];
        $unknown = self::decode(['key' => self::CHILD_KEY, 'type' => 'removed_type', 'version' => 1, 'fields' => ['subtitle' => 'Kept'], 'translated' => ['subtitle' => $state]]);
        $block = self::block('teaser', 3, ['heading' => ['en_US' => 'Kept', 'da_DK' => 'Beholdt'], 'subtitle' => ['en_US' => 'Removed']], [$unknown]);
        $block['translated'] = ['heading' => $state, 'subtitle' => $state];

        $upgraded = self::upgrader()->upgradeTree(self::decode([
            'layout' => ['code' => 'landing', 'version' => 2, 'fields' => ['transparentHeader' => true, 'removed' => false], 'translated' => ['removed' => $state]],
            'blocks' => [$block],
        ]), 'page');

        self::assertSame(
            '{"blocks":[{"children":[{"fields":{"subtitle":"Kept"},"key":"' . self::CHILD_KEY . '","translated":{"subtitle":{"da_DK":{"fingerprint":"abc","from":"en_US"}}},"type":"removed_type","version":1}],"conditions":[],"fields":{"heading":{"da_DK":"Beholdt","en_US":"Kept"}},"key":"' . self::KEY . '","style":null,"translated":{"heading":{"da_DK":{"fingerprint":"abc","from":"en_US"}}},"type":"teaser","version":3}],"layout":{"code":"landing","fields":{"transparentHeader":true},"translated":{},"version":2}}',
            CanonicalJson::encode($upgraded->data),
        );
    }

    /**
     * @test
     */
    public function it_reads_an_entrys_block_tree_from_its_rows_upgraded_and_leaves_the_rows_as_they_are(): void
    {
        $page = new Page();
        $stored = new StoredBlock(self::KEY, 'teaser', 1, fields: ['title' => ['en_US' => 'Hello']]);
        $page->setBlockTree(new StoredTree(new StoredLayout('landing', 1, ['transparent' => true]), [$stored]));
        $rows = $page->getBlockTreeJson();
        $upgrader = self::upgrader([
            'block_type:teaser:1' => new RenameField('title', 'headline'),
            'block_type:teaser:2' => new RenameField('headline', 'heading'),
            'layout:page:landing:1' => new RenameField('transparent', 'transparentHeader'),
        ]);

        $tree = $upgrader->readBlockTree($page);

        self::assertSame(
            '{"layout":{"code":"landing","version":2,"fields":{"transparentHeader":true},"translated":{}},"blocks":[{"key":"' . self::KEY . '","type":"teaser","version":3,"style":null,"fields":{"heading":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}',
            (new StoredTreeSerializer())->toJson($tree),
        );
        self::assertSame($rows, $page->getBlockTreeJson());
    }

    /**
     * @param array<string, UpgraderInterface> $upgraders
     */
    private static function upgrader(array $upgraders = []): StoredDataUpgrader
    {
        return new StoredDataUpgrader(
            new BlockTypeRegistry(['teaser' => Teaser::class], new FieldReader()),
            new LayoutRegistry(['page' => ['landing' => Landing::class], 'article' => ['landing' => Landing::class]], new FieldReader()),
            new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'], 'article' => ['model' => Article::class, 'translation' => EntryTranslation::class, 'resource' => 'app.article']]),
            new ServiceLocator(array_map(static fn (UpgraderInterface $upgrader): \Closure => static fn (): UpgraderInterface => $upgrader, $upgraders)),
        );
    }

    /**
     * Data as CanonicalJson::decode() gives it
     *
     * @return array<array-key, mixed>
     */
    private static function decode(mixed $data): array
    {
        $decoded = CanonicalJson::decode(CanonicalJson::encode($data));
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * A block as CanonicalJson::decode() gives it
     *
     * @param array<string, mixed> $fields
     * @param list<mixed> $children
     *
     * @return array<string, mixed>
     */
    private static function block(string $type, int $version, array $fields = [], array $children = [], string $key = self::KEY): array
    {
        /** @var array<string, mixed> $block */
        $block = CanonicalJson::decode(CanonicalJson::encode([
            'key' => $key,
            'type' => $type,
            'version' => $version,
            'style' => null,
            'fields' => (object) $fields,
            'translated' => new \stdClass(),
            'conditions' => [],
            'children' => $children,
        ]));

        return $block;
    }

    /**
     * Upgraded blocks as the stored format writes them, in a tree without a layout
     *
     * @param array<array-key, mixed> $blocks
     */
    private static function encode(array $blocks): string
    {
        $serializer = new StoredTreeSerializer();

        return $serializer->toJson(new StoredTree(null, array_map($serializer->denormalizeBlock(...), array_values($blocks))));
    }
}

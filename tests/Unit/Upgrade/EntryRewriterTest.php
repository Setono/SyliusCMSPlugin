<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Upgrade;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManager;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\RenameField;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Upgrade\EntryRewriter;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgrader;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class EntryRewriterTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /** @var ObjectProphecy<DraftRepositoryInterface> */
    private ObjectProphecy $drafts;

    protected function setUp(): void
    {
        $this->drafts = $this->prophesize(DraftRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_writes_rows_that_need_upgrading_back_upgraded(): void
    {
        $page = self::page(self::teaser(1, 'title'));

        $rewrite = $this->rewriter()->rewrite($page);

        self::assertTrue($rewrite->blockTree);
        self::assertFalse($rewrite->draft);
        self::assertSame([], $rewrite->newer);
        self::assertSame(
            '{"layout":null,"blocks":[{"key":"' . self::KEY . '","type":"teaser","version":3,"style":null,"fields":{"heading":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}',
            $page->getBlockTreeJson(),
        );
    }

    /**
     * @test
     */
    public function it_leaves_rows_that_are_up_to_date(): void
    {
        $page = self::page(self::teaser(3, 'heading'), published: true);
        $rows = $page->getBlockTreeJson();
        $this->drafts->findOneByEntry($page)->willReturn(null);

        $rewrite = $this->rewriter()->rewrite($page);

        self::assertFalse($rewrite->blockTree);
        self::assertFalse($rewrite->draft);
        self::assertSame($rows, $page->getBlockTreeJson());
    }

    /**
     * @test
     */
    public function it_writes_a_draft_that_needs_upgrading_back_upgraded_and_leaves_the_rows(): void
    {
        $page = self::page(self::teaser(3, 'heading'), published: true);
        $rows = $page->getBlockTreeJson();
        $draft = self::draft($page, self::teaser(1, 'title', 'Drafted'));
        $this->drafts->findOneByEntry($page)->willReturn($draft);

        $rewrite = $this->rewriter()->rewrite($page);

        self::assertFalse($rewrite->blockTree);
        self::assertTrue($rewrite->draft);
        self::assertStringEndsWith(
            '"tree":{"layout":null,"blocks":[{"key":"' . self::KEY . '","type":"teaser","version":3,"style":null,"fields":{"heading":{"en_US":"Drafted"}},"translated":{},"conditions":[],"children":[]}]}}',
            $draft->getContent(),
        );
        self::assertSame($rows, $page->getBlockTreeJson());
    }

    /**
     * @test
     */
    public function it_leaves_a_draft_that_is_up_to_date(): void
    {
        $page = self::page(self::teaser(3, 'heading'), published: true);
        $draft = self::draft($page, self::teaser(3, 'heading', 'Drafted'));
        $content = $draft->getContent();
        $this->drafts->findOneByEntry($page)->willReturn($draft);

        $rewrite = $this->rewriter()->rewrite($page);

        self::assertFalse($rewrite->draft);
        self::assertSame($content, $draft->getContent());
    }

    /**
     * @test
     */
    public function it_tells_what_is_newer_than_the_code_keeps_it_and_leaves_a_draft_of_a_newer_content_type_version_as_it_is(): void
    {
        $page = self::page(self::teaser(4, 'subtitle'), published: true);
        $rows = $page->getBlockTreeJson();
        $draft = self::draft($page, self::teaser(1, 'title', 'Drafted'));
        $draft->setContent(str_replace('"contentType":"page","version":1', '"contentType":"page","version":2', $draft->getContent()));
        $content = $draft->getContent();
        $this->drafts->findOneByEntry($page)->willReturn($draft);

        $rewrite = $this->rewriter()->rewrite($page);

        self::assertEquals([new NewerVersion(Kind::BlockType, 'teaser', 4, 3), new NewerVersion(Kind::ContentType, 'page', 2, 1)], $rewrite->newer);
        self::assertFalse($rewrite->blockTree);
        self::assertSame($rows, $page->getBlockTreeJson());
        self::assertFalse($rewrite->draft);
        self::assertSame($content, $draft->getContent());
    }

    /**
     * @test
     */
    public function it_writes_back_a_draft_with_a_newer_block_upgrading_the_rest_and_keeping_the_newer_block_as_it_is(): void
    {
        $page = self::page(self::teaser(3, 'heading'), published: true);
        $newer = new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50', 'teaser', 4, fields: ['subtitle' => ['en_US' => 'From the future']]);
        $draft = self::draft($page, new StoredTree(null, [...self::teaser(1, 'title', 'Drafted')->blocks, $newer]));
        $this->drafts->findOneByEntry($page)->willReturn($draft);

        $rewrite = $this->rewriter()->rewrite($page);

        self::assertEquals([new NewerVersion(Kind::BlockType, 'teaser', 4, 3)], $rewrite->newer);
        self::assertTrue($rewrite->draft);
        self::assertStringEndsWith(
            '"blocks":[{"key":"' . self::KEY . '","type":"teaser","version":3,"style":null,"fields":{"heading":{"en_US":"Drafted"}},"translated":{},"conditions":[],"children":[]},{"key":"0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50","type":"teaser","version":4,"style":null,"fields":{"subtitle":{"en_US":"From the future"}},"translated":{},"conditions":[],"children":[]}]}}',
            $draft->getContent(),
        );
    }

    private function rewriter(): EntryRewriter
    {
        $upgrader = new StoredDataUpgrader(
            new BlockTypeRegistry(['teaser' => Teaser::class], new FieldReader()),
            new LayoutRegistry([], new FieldReader()),
            self::contentTypes(),
            new ServiceLocator([
                'block_type:teaser:1' => static fn (): RenameField => new RenameField('title', 'headline'),
                'block_type:teaser:2' => static fn (): RenameField => new RenameField('headline', 'heading'),
            ]),
        );

        $workingCopies = new WorkingCopyManager(
            $this->drafts->reveal(),
            new /** @implements FactoryInterface<DraftInterface> */ class() implements FactoryInterface {
                public function createNew(): DraftInterface
                {
                    return new Draft();
                }
            },
            new EntryNormalizer(self::contentTypes()),
            new EntryDenormalizer(self::contentTypes()),
            $this->prophesize(ObjectManager::class)->reveal(),
            $upgrader,
        );

        return new EntryRewriter($upgrader, self::contentTypes(), $this->drafts->reveal(), $workingCopies, new EntryNormalizer(self::contentTypes()));
    }

    /**
     * A Teaser block at $version, whose heading is stored under the name $field, as that version named it
     */
    private static function teaser(int $version, string $field, string $text = 'Hello'): StoredTree
    {
        return new StoredTree(null, [new StoredBlock(self::KEY, 'teaser', $version, fields: [$field => ['en_US' => $text]])]);
    }

    private static function page(StoredTree $tree, bool $published = false): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about-us');
        $page->setBlockTree($tree);

        if ($published) {
            $page->publish(new \DateTimeImmutable('2026-11-01 10:00:00', new \DateTimeZone('UTC')));
        }

        return $page;
    }

    private static function draft(Page $page, StoredTree $tree): DraftInterface
    {
        $copy = clone $page;
        $copy->setBlockTree($tree);

        $draft = new Draft();
        $draft->setEntry($page);
        $draft->setContent(CanonicalJson::encode((new EntryNormalizer(self::contentTypes()))->normalize($copy)));

        return $draft;
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
        ]);
    }
}

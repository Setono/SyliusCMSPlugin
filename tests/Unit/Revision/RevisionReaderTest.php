<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Revision;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Revision;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Revision\RevisionReader;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Upgrade\Kind;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;

final class RevisionReaderTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /** @var ObjectProphecy<StoredDataUpgraderInterface> */
    private ObjectProphecy $upgrader;

    private Page $page;

    protected function setUp(): void
    {
        $this->upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $this->page = self::page('About our shop', 'Now');
    }

    /**
     * @test
     */
    public function it_reads_a_revisions_content_through_the_upgraders(): void
    {
        $revision = $this->revisionOf(self::page('About us', 'Then'));
        $saved = CanonicalJson::decode($revision->getContent());
        self::assertIsArray($saved);
        $upgraded = new Upgraded(['upgraded' => true], [new NewerVersion(Kind::BlockType, 'heading', 2, 1)]);
        $this->upgrader->upgradeEntry($saved)->willReturn($upgraded);

        self::assertSame($upgraded, $this->reader()->read($revision));
    }

    /**
     * @test
     */
    public function it_builds_a_copy_of_the_entry_that_holds_the_revisions_upgraded_content_and_leaves_the_entry_alone(): void
    {
        $revision = $this->revisionOf(self::page('About us', 'Then'));
        $this->upgrader->upgradeEntry(Argument::type('array'))->will(static function (array $arguments): Upgraded {
            /** @var array{translations: array{en_US: array{fields: array<string, mixed>}}} $data */
            $data = $arguments[0];
            $data['translations']['en_US']['fields']['title'] = 'About us, upgraded';

            return new Upgraded($data);
        });

        $entry = $this->reader()->entry($revision);

        self::assertNotSame($this->page, $entry);
        self::assertSame($this->page->getUuid(), $entry->getUuid());
        self::assertSame('About us, upgraded', $entry->getTranslation('en_US')->getTitle());
        self::assertSame(['en_US' => 'Then'], $entry->getBlockTree()->blocks[0]->fields['text'] ?? null);

        self::assertSame('About our shop', $this->page->getTranslation('en_US')->getTitle());
        self::assertSame(['en_US' => 'Now'], $this->page->getBlockTree()->blocks[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_reads_content_that_is_not_an_entry_as_an_empty_one_which_no_entry_is_built_from(): void
    {
        $revision = new Revision();
        $revision->setEntry($this->page);
        $revision->setContent('"About us"');
        $this->upgrader->upgradeEntry(Argument::any())->shouldNotBeCalled();

        self::assertEquals(new Upgraded([]), $this->reader()->read($revision));

        $this->expectException(InvalidEntryFormatException::class);
        $this->reader()->entry($revision);
    }

    private function reader(): RevisionReader
    {
        return new RevisionReader($this->upgrader->reveal(), new EntryDenormalizer(self::contentTypes()));
    }

    /**
     * A revision of $this->page that holds the content of $content
     */
    private function revisionOf(Page $content): Revision
    {
        $uuid = new \ReflectionProperty(Page::class, 'uuid');
        $uuid->setValue($content, $this->page->getUuid());

        $revision = new Revision();
        $revision->setEntry($this->page);
        $revision->setContent(CanonicalJson::encode((new EntryNormalizer(self::contentTypes()))->normalize($content)));

        return $revision;
    }

    private static function page(string $title, string $heading): Page
    {
        $block = new StoredBlock(self::KEY, 'heading', 1);
        $block->setValue('text', 'en_US', $heading);

        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle($title);
        $page->setSlug('about-us');
        $page->setBlockTree(new StoredTree(null, [$block]));

        return $page;
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]);
    }
}

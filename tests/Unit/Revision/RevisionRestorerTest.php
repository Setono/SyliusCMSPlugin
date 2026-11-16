<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Revision;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Revision;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\LoadedWorkingCopy;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Revision\RevisionReader;
use Setono\SyliusCMSPlugin\Revision\RevisionRestorer;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Upgrade\Upgraded;

final class RevisionRestorerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_replaces_the_entrys_working_copy_with_the_revisions_upgraded_content(): void
    {
        $page = new Page();
        $revision = new Revision();
        $revision->setEntry($page);
        $revision->setContent('{"version":1}');

        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->upgradeEntry(['version' => 1])->willReturn(new Upgraded(['version' => 2]));

        $loaded = new LoadedWorkingCopy(clone $page, 4);
        $autosave = $this->prophesize(AutosaveInterface::class);
        $autosave->replace($page, ['version' => 2])->willReturn($loaded)->shouldBeCalledOnce();

        $restorer = new RevisionRestorer(
            new RevisionReader($upgrader->reveal(), new EntryDenormalizer(new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            ]))),
            $autosave->reveal(),
        );

        self::assertSame($loaded, $restorer->restore($revision));
    }
}

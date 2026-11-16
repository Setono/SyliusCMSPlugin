<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Routing;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\OldSlug;
use Setono\SyliusCMSPlugin\Model\OldSlugInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Repository\OldSlugRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\OldSlugRecorder;
use Sylius\Resource\Factory\FactoryInterface;

final class OldSlugRecorderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<OldSlugRepositoryInterface> */
    private ObjectProphecy $oldSlugs;

    /** @var ObjectProphecy<ObjectManager> */
    private ObjectProphecy $manager;

    /** @var list<OldSlugInterface> what the manager was given to persist */
    private array $persisted = [];

    private Page $page;

    protected function setUp(): void
    {
        $this->oldSlugs = $this->prophesize(OldSlugRepositoryInterface::class);
        $this->manager = $this->prophesize(ObjectManager::class);
        $persisted = &$this->persisted;
        $this->manager->persist(Argument::type(OldSlugInterface::class))->will(static function (array $arguments) use (&$persisted): void {
            $persisted[] = $arguments[0];
        });
        $this->page = new Page();
    }

    /**
     * @test
     */
    public function it_records_the_slug_of_each_locale_whose_slug_changed(): void
    {
        $this->oldSlugs->findByEntry($this->page)->willReturn([]);

        $this->recorder()->record(
            $this->page,
            ['da_DK' => 'om-os', 'de_DE' => 'uber-uns', 'en_US' => 'about'],
            ['da_DK' => 'om-os', 'de_DE' => 'ueber-uns', 'en_US' => 'about-us'],
        );

        self::assertSame([['de_DE', 'uber-uns'], ['en_US', 'about']], $this->persistedSlugs());
        foreach ($this->persisted as $oldSlug) {
            self::assertSame($this->page, $oldSlug->getEntry());
        }
    }

    /**
     * @test
     */
    public function it_reads_nothing_when_no_slug_changed(): void
    {
        $this->oldSlugs->findByEntry($this->page)->shouldNotBeCalled();

        $this->recorder()->record($this->page, ['en_US' => 'about-us'], ['en_US' => 'about-us', 'da_DK' => 'om-os']);

        self::assertSame([], $this->persisted);
    }

    /**
     * There's no URL to redirect it to
     *
     * @test
     */
    public function it_keeps_no_slug_of_a_locale_that_lost_its_slug(): void
    {
        $this->oldSlugs->findByEntry($this->page)->shouldNotBeCalled();

        $this->recorder()->record($this->page, ['da_DK' => 'om-os', 'en_US' => 'about-us'], ['en_US' => 'about-us']);

        self::assertSame([], $this->persisted);
    }

    /**
     * @test
     */
    public function it_records_a_slug_once(): void
    {
        $this->oldSlugs->findByEntry($this->page)->willReturn([self::oldSlug('en_US', 'about'), self::oldSlug('da_DK', 'about-us')]);

        $this->recorder()->record($this->page, ['da_DK' => 'om', 'en_US' => 'about'], ['da_DK' => 'om-os', 'en_US' => 'about-us']);

        self::assertSame([['da_DK', 'om']], $this->persistedSlugs());
    }

    /**
     * Its URL is the entry's current one again, which mustn't redirect anywhere
     *
     * @test
     */
    public function a_slug_the_entry_has_again_is_no_longer_an_old_one(): void
    {
        $again = self::oldSlug('en_US', 'about');
        $other = self::oldSlug('en_US', 'about-our-shop');
        $otherLocale = self::oldSlug('da_DK', 'about');
        $this->oldSlugs->findByEntry($this->page)->willReturn([$again, $other, $otherLocale]);
        $this->manager->remove($again)->shouldBeCalledOnce();
        $this->manager->remove($other)->shouldNotBeCalled();
        $this->manager->remove($otherLocale)->shouldNotBeCalled();

        $this->recorder()->record($this->page, ['en_US' => 'about-us'], ['en_US' => 'about']);

        self::assertSame([['en_US', 'about-us']], $this->persistedSlugs());
    }

    private function recorder(): OldSlugRecorder
    {
        $factory = $this->prophesize(FactoryInterface::class);
        $factory->createNew()->will(static fn (): OldSlug => new OldSlug());

        /** @var FactoryInterface<OldSlugInterface> $oldSlugFactory */
        $oldSlugFactory = $factory->reveal();

        return new OldSlugRecorder($this->oldSlugs->reveal(), $oldSlugFactory, $this->manager->reveal());
    }

    /**
     * @return list<array{string, string}>
     */
    private function persistedSlugs(): array
    {
        return array_map(static fn (OldSlugInterface $oldSlug): array => [$oldSlug->getLocaleCode(), $oldSlug->getSlug()], $this->persisted);
    }

    private static function oldSlug(string $localeCode, string $slug): OldSlug
    {
        $oldSlug = new OldSlug();
        $oldSlug->setLocaleCode($localeCode);
        $oldSlug->setSlug($slug);

        return $oldSlug;
    }
}

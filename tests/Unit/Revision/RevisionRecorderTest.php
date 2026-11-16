<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Revision;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Revision;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Revision\RevisionRecorder;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class RevisionRecorderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ObjectManager> */
    private ObjectProphecy $manager;

    private TokenStorage $tokenStorage;

    private Revision $revision;

    protected function setUp(): void
    {
        $this->manager = $this->prophesize(ObjectManager::class);
        $this->tokenStorage = new TokenStorage();
        $this->revision = new Revision();
    }

    /**
     * @test
     */
    public function it_stores_the_entrys_content_in_the_entry_format_with_who_published_it_and_when(): void
    {
        $this->signIn('jdoe', 'John', 'Doe');
        $page = self::page();
        $this->manager->persist($this->revision)->shouldBeCalledOnce();

        $this->recorder()->record($page, new \DateTimeImmutable('2026-11-14 12:30:00', new \DateTimeZone('Europe/Copenhagen')));

        self::assertSame($page, $this->revision->getEntry());
        self::assertSame(CanonicalJson::encode(self::normalizer()->normalize($page)), $this->revision->getContent());
        self::assertStringContainsString('"title":"About us"', $this->revision->getContent());
        self::assertSame('2026-11-14T11:30:00+00:00', $this->revision->getPublishedAt()->format(\DATE_ATOM));
        self::assertSame('John Doe', $this->revision->getPublishedBy());
        self::assertFalse($this->revision->isScheduled());
    }

    /**
     * @test
     */
    public function it_stores_a_scheduled_publish_with_who_scheduled_it(): void
    {
        $this->signIn('worker');

        $this->recorder()->record(self::page(), new \DateTimeImmutable('2026-11-14 12:30:00'), true, 'Jane Roe');

        self::assertSame('Jane Roe', $this->revision->getPublishedBy());
        self::assertTrue($this->revision->isScheduled());
    }

    /**
     * @test
     */
    public function it_stores_no_one_as_who_published_it_when_no_one_is_signed_in(): void
    {
        $this->recorder()->record(self::page(), new \DateTimeImmutable('2026-11-14 12:30:00'));

        self::assertNull($this->revision->getPublishedBy());
    }

    private function signIn(string $username, ?string $firstName = null, ?string $lastName = null): void
    {
        $admin = new AdminUser();
        $admin->setUsername($username);
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);
        $this->tokenStorage->setToken(new UsernamePasswordToken($admin, 'admin', []));
    }

    private function recorder(): RevisionRecorder
    {
        $factory = $this->prophesize(FactoryInterface::class);
        $factory->createNew()->willReturn($this->revision);

        /** @var FactoryInterface<RevisionInterface> $revisionFactory */
        $revisionFactory = $factory->reveal();

        return new RevisionRecorder($revisionFactory, self::normalizer(), $this->manager->reveal(), $this->tokenStorage);
    }

    private static function normalizer(): EntryNormalizer
    {
        return new EntryNormalizer(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
        ]));
    }

    private static function page(): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about-us');

        return $page;
    }
}

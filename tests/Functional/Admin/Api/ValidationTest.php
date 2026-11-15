<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Invalid content saves, entry_validate lists its errors, and publish refuses it. See "Validation" in docs/spec/publishing.md
 */
final class ValidationTest extends BuilderApiTestCase
{
    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const COLUMNS = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    private const COLUMN = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e51';

    private const STRAY_COLUMN = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e52';

    private const BANNER = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e53';

    private const GROUP = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e54';

    private const GROUP_COPY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e55';

    private const BANNER_COPY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e56';

    /**
     * @test
     */
    public function an_invalid_tree_autosaves(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::HEADING, 'field' => 'text', 'locale' => 'en_US', 'value' => null],
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'block' => [
                'key' => self::STRAY_COLUMN, 'type' => 'column', 'version' => 1, 'style' => null, 'fields' => [], 'translated' => [], 'conditions' => [], 'children' => [],
            ]],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'unpublished_changes', 'lockToken' => 1], $response);
        self::assertStringContainsString(
            sprintf('"blocks":[{"key":"%s","type":"heading","version":1,"style":null,"fields":{},', self::HEADING),
            (string) self::drafts()->findOneByEntry(self::reloadPage($page))?->getContent(),
        );
        self::assertStringContainsString(sprintf('{"key":"%s","type":"column"', self::STRAY_COLUMN), (string) self::drafts()->findOneByEntry(self::reloadPage($page))?->getContent());
    }

    /**
     * @test
     */
    public function a_block_that_holds_a_block_of_a_type_the_code_no_longer_has_can_be_edited_and_duplicated(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('About us', 'about-us', [
            new StoredBlock(self::GROUP, 'group', 1, children: [new StoredBlock(self::BANNER, 'banner', 1, fields: ['title' => 'Winter sale'])]),
        ], published: true);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::GROUP, 'field' => 'fullWidth', 'locale' => null, 'value' => true],
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'after' => self::GROUP, 'block' => [
                'key' => self::GROUP_COPY, 'type' => 'group', 'version' => 1, 'style' => null, 'fields' => ['fullWidth' => true], 'translated' => [], 'conditions' => [], 'children' => [
                    ['key' => self::BANNER_COPY, 'type' => 'banner', 'version' => 1, 'style' => null, 'fields' => ['title' => 'Winter sale'], 'translated' => [], 'conditions' => [], 'children' => []],
                ],
            ]],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'unpublished_changes', 'lockToken' => 1], $response);

        $draft = (string) self::drafts()->findOneByEntry(self::reloadPage($page))?->getContent();
        self::assertStringContainsString(sprintf('{"key":"%s","type":"group","version":1,"style":null,"fields":{"fullWidth":true},', self::GROUP), $draft);
        self::assertStringContainsString(sprintf('{"key":"%s","type":"banner","version":1,"style":null,"fields":{"title":"Winter sale"},', self::BANNER_COPY), $draft);
    }

    /**
     * @test
     */
    public function entry_validate_lists_the_working_copys_errors_by_block_key_and_field(): void
    {
        $client = self::createClient();
        $page = self::createInvalidPage();
        self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => self::expectedErrors()], $response);
    }

    /**
     * @test
     */
    public function entry_validate_answers_no_errors_for_valid_content(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => []], $response);
    }

    /**
     * @test
     */
    public function entry_validate_lists_a_content_type_fields_errors_by_entry_field(): void
    {
        $client = self::createClient();

        /** @var FactoryInterface<Announcement> $factory */
        $factory = self::getContainer()->get('app.factory.announcement');
        $announcement = $factory->createNew();
        $announcement->setTitle('Winter sale');

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('app.manager.announcement');
        $manager->persist($announcement);
        $manager->flush();

        self::openBuilder($client, $announcement);
        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $announcement->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => [['block' => null, 'field' => 'subtitle', 'locale' => null, 'message' => 'This field is required.']]], $response);
    }

    /**
     * @test
     */
    public function entry_validate_validates_the_draft_of_a_published_entry(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::HEADING, 'field' => 'text', 'locale' => 'en_US', 'value' => null],
        ]], $csrfToken);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertSame(['errors' => [['block' => self::HEADING, 'field' => 'text', 'locale' => 'en_US', 'message' => 'This field is required.']]], $response);
    }

    /**
     * @test
     */
    public function entry_validate_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function entry_publish_refuses_invalid_content_with_its_errors_and_changes_nothing(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $page = self::createInvalidPage();
        $csrfToken = self::openBuilder($client, $page);
        $rows = self::reloadPage($page)->getBlockTreeJson();

        $vetoed = false;
        self::eventDispatcher()->addListener(BeforeEntryPublish::class, static function () use (&$vetoed): void {
            $vetoed = true;
        });

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => '4 errors need fixing', 'errors' => self::expectedErrors()], $response);
        self::assertFalse($vetoed, 'BeforeEntryPublish is dispatched only for content that passes validation');

        $reloaded = self::reloadPage($page);
        self::assertFalse($reloaded->isPublished());
        self::assertSame($rows, $reloaded->getBlockTreeJson());
    }

    /**
     * @test
     */
    public function entry_publish_keeps_the_draft_of_a_published_entry_it_refuses(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        $rows = self::reloadPage($page)->getBlockTreeJson();
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::HEADING, 'field' => 'text', 'locale' => 'en_US', 'value' => null],
        ]], $csrfToken);
        $draft = self::drafts()->findOneByEntry(self::reloadPage($page))?->getContent();

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'One error needs fixing', 'errors' => [['block' => self::HEADING, 'field' => 'text', 'locale' => 'en_US', 'message' => 'This field is required.']]], $response);
        self::assertSame($rows, self::reloadPage($page)->getBlockTreeJson());
        self::assertSame($draft, self::drafts()->findOneByEntry(self::reloadPage($page))?->getContent());

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-heading', 'Hello');
    }

    /**
     * A never-published page with an empty heading, Columns with one Column, a Column at the top level and a block of a
     * type the code no longer has
     */
    private static function createInvalidPage(): PageInterface
    {
        return self::createPageWithBlocks('About us', 'about-us', [
            new StoredBlock(self::HEADING, 'heading', 1),
            new StoredBlock(self::COLUMNS, 'columns', 1, children: [new StoredBlock(self::COLUMN, 'column', 1)]),
            new StoredBlock(self::STRAY_COLUMN, 'column', 1),
            new StoredBlock(self::BANNER, 'banner', 1, fields: ['title' => 'Winter sale']),
        ]);
    }

    /**
     * @return list<array{block: string|null, field: string|null, locale: string|null, message: string}>
     */
    private static function expectedErrors(): array
    {
        return [
            ['block' => self::HEADING, 'field' => 'text', 'locale' => 'en_US', 'message' => 'This field is required.'],
            ['block' => self::COLUMNS, 'field' => null, 'locale' => null, 'message' => 'Needs at least 2 Column blocks inside.'],
            ['block' => self::STRAY_COLUMN, 'field' => null, 'locale' => null, 'message' => 'Column can\'t go here.'],
            ['block' => self::BANNER, 'field' => null, 'locale' => null, 'message' => 'Its block type "banner" no longer exists, so it isn\'t shown.'],
        ];
    }

    private static function drafts(): DraftRepositoryInterface
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->clear();

        /** @var DraftRepositoryInterface $drafts */
        $drafts = self::getContainer()->get('setono_sylius_cms.repository.draft');

        return $drafts;
    }

    private static function eventDispatcher(): EventDispatcherInterface
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');

        return $eventDispatcher;
    }
}

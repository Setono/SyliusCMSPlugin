<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\CampaignLayoutKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Publish, and entry_validate, check the fields of the tree's layout as they check a block's, and every block's
 * visibility conditions. See "Validation" in docs/spec/publishing.md
 */
final class LayoutFieldAndConditionValidationTest extends BuilderApiTestCase
{
    use Customers;

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    protected static function getKernelClass(): string
    {
        return CampaignLayoutKernel::class;
    }

    /**
     * @test
     */
    public function it_refuses_to_publish_an_empty_required_layout_field_and_points_at_the_field_in_the_layout(): void
    {
        $client = self::createClient();
        $page = self::createPage('Winter sale', 'winter-sale', 'Hello');
        $page->setBlockTree(new StoredTree(new StoredLayout('campaign', 1), $page->getBlockTree()->blocks));
        self::flush();
        $csrfToken = self::openBuilder($client, $page);
        $errors = [['block' => null, 'field' => 'headline', 'locale' => 'en_US', 'message' => 'This field is required.', 'layout' => true]];

        self::assertSame(['errors' => $errors], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid())));

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'One error needs fixing', 'errors' => $errors], $response);
        self::assertFalse(self::reloadPage($page)->isPublished());

        // Filled in, it publishes
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_layout_value', 'layout' => 'campaign', 'field' => 'headline', 'locale' => 'en_US', 'value' => 'Up to 50% off'],
        ]], $csrfToken);
        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertTrue(self::reloadPage($page)->isPublished());
    }

    /**
     * @test
     */
    public function it_refuses_to_publish_a_date_window_condition_that_ends_before_it_starts_and_points_at_its_end(): void
    {
        $client = self::createClient();
        $heading = self::heading(self::HEADING, 'Christmas offers');
        $heading->conditions = [
            ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['@guest']]],
            ['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-12-24T00:00:00Z', 'until' => '2026-12-01T00:00:00Z']],
        ];
        $page = self::createPageWithBlocks('Christmas', 'christmas', [$heading]);
        $csrfToken = self::openBuilder($client, $page);
        $errors = [['block' => self::HEADING, 'field' => 'until', 'locale' => null, 'message' => 'Until must come after From.', 'condition' => 1]];

        self::assertSame(['errors' => $errors], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid())));

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'One error needs fixing', 'errors' => $errors], $response);
        self::assertFalse(self::reloadPage($page)->isPublished());
    }

    /**
     * A deleted record in a condition is a broken reference, which the admin flags, not a value publish refuses (ADR
     * 0002). See "Validation" in docs/spec/publishing.md
     *
     * @test
     */
    public function it_publishes_a_condition_naming_a_deleted_channel_or_customer_group_and_flags_the_entry(): void
    {
        $client = self::createClient();
        self::createChannel('OUTLET');
        self::createCustomerGroup('wholesale', 'Wholesale');
        $heading = self::heading(self::HEADING, 'Outlet offers');
        $heading->conditions = [
            ['kind' => 'channel', 'negate' => false, 'params' => ['channels' => ['WEB', 'OUTLET']]],
            ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['@guest', 'wholesale']]],
        ];
        $page = self::createPageWithBlocks('Outlet', 'outlet', [$heading]);
        self::delete('sylius.repository.channel', 'sylius.manager.channel', 'OUTLET');
        self::delete('sylius.repository.customer_group', 'sylius.manager.customer_group', 'wholesale');
        $csrfToken = self::openBuilder($client, $page);

        self::assertSame(['errors' => []], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid())));

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertTrue(self::reloadPage($page)->isPublished());
        self::assertEquals(new ProblemCount(2, 0), self::storedCount($page));
    }

    private static function delete(string $repositoryId, string $managerId, string $code): void
    {
        /** @var RepositoryInterface<ResourceInterface> $repository */
        $repository = self::getContainer()->get($repositoryId);
        $record = $repository->findOneBy(['code' => $code]);
        self::assertNotNull($record);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get($managerId);
        $manager->remove($record);
        $manager->flush();
    }

    private static function flush(): void
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }
}

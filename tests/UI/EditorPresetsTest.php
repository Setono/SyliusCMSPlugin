<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\EditorPresetSaverInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * An editor ⌘-clicks two blocks, saves them as the preset "Promo" in "Campaigns", inserts it into another page, and
 * renames it on the Presets screen. See "Editor presets" in docs/spec/layouts-and-presets.md and "Presets" in
 * docs/spec/admin.md
 */
final class EditorPresetsTest extends UITestCase
{
    private const SUMMER = '0193a5be-0000-7000-8000-000000000001';

    private const MIDDLE = '0193a5be-0000-7000-8000-000000000002';

    private const SHOP_NOW = '0193a5be-0000-7000-8000-000000000003';

    /**
     * @test
     */
    public function it_lets_an_admin_user_save_two_blocks_as_a_preset_insert_it_into_another_page_and_rename_it_on_the_presets_screen(): void
    {
        $summer = self::createPageWithBlocks('Summer', 'summer', [self::heading(self::SUMMER, 'Summer sale'), self::heading(self::MIDDLE, 'Middle'), self::heading(self::SHOP_NOW, 'Shop now')]);
        $about = self::createPage('About us', 'about-us');
        $this->signInAsAdmin();

        // ⌘-click two blocks and save them as a preset with ★
        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $summer->getUuid()));
        $tree = $page->getByRole('navigation', ['name' => 'Blocks']);
        $tree->getByRole('button', ['name' => 'Heading Summer sale'])->click();
        $tree->getByRole('button', ['name' => 'Heading Shop now'])->click(['modifiers' => ['ControlOrMeta']]);
        $selection = $page->getByRole('complementary');
        Expect::locator($selection->getByRole('heading', ['name' => '2 blocks selected']))->toBeVisible();
        $selection->getByRole('button', ['name' => 'Save as preset'])->click();

        $dialog = $page->getByRole('dialog', ['name' => 'Save as preset']);
        $dialog->getByRole('textbox', ['name' => 'Name'])->fill('Promo');
        $dialog->getByRole('combobox', ['name' => 'Category'])->fill('Campaigns');
        $dialog->getByRole('button', ['name' => 'Save preset'])->click();
        Expect::locator($dialog)->toHaveCount(0);

        $promo = self::editorPreset('Promo');
        self::assertSame('Campaigns', $promo->category);
        self::assertSame([self::SUMMER, self::SHOP_NOW], array_map(static fn (StoredBlock $block): string => $block->key, $promo->blocks));

        // Insert it into another page from the Presets tab
        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $about->getUuid()));
        Expect::locator($page->frameLocator('iframe[title="Preview"]')->locator('.cms-tree'))->toHaveCount(1);
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $picker = $page->getByRole('dialog', ['name' => 'Add block']);
        $picker->getByRole('tab', ['name' => 'Presets'])->click();
        Expect::locator($picker->getByRole('group', ['name' => 'Campaigns']))->toBeVisible();
        $picker->getByRole('button', ['name' => 'Insert Promo'])->click();

        Expect::locator($picker)->toHaveCount(0);
        $tree = $page->getByRole('navigation', ['name' => 'Blocks']);
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Summer sale']))->toBeVisible();
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Shop now']))->toBeVisible();
        Expect::locator($tree->getByRole('button', ['name' => 'Heading Middle']))->toHaveCount(0);
        Expect::locator($page->getByRole('status'))->toHaveText('Saved');
        self::assertSame([['en_US' => 'Summer sale'], ['en_US' => 'Shop now']], array_map(static fn (StoredBlock $block): mixed => $block->fields['text'] ?? null, self::reloadPage($about)->getBlockTree()->blocks));

        // Rename it on the Presets screen
        $page = $this->visit('/admin/cms/presets');
        $page->getByRole('link', ['name' => 'Block presets'])->click();
        $row = $page->locator(sprintf('[data-preset="%s"]', $promo->id()));
        $row->getByRole('button', ['name' => 'Edit Promo'])->click();
        $edit = $page->getByRole('dialog', ['name' => 'Edit Promo']);
        $edit->getByRole('textbox', ['name' => 'Name'])->fill('Spring promo');
        $edit->getByRole('button', ['name' => 'Save'])->click();

        Expect::locator($page->locator('.sylius-flash-message'))->toContainText('The preset has been saved.');
        Expect::locator($page->locator(sprintf('[data-preset="%s"] [data-name]', $promo->id())))->toHaveText('Spring promo');
        self::assertSame('Spring promo', self::editorPreset('Spring promo')->name);
    }

    /**
     * A preset left out where it doesn't fit, such as one that needs a product page in a page, still shows its issues and
     * is edited, deleted and downloaded there. See "Presets" in docs/spec/builder.md
     *
     * @test
     */
    public function it_lets_an_admin_user_delete_an_editor_preset_the_presets_tab_leaves_out_seeing_its_issues(): void
    {
        $note = new StoredBlock('0193a5be-0000-7000-8000-000000000011', 'app_product_note', 1);
        $note->setValue('note', 'en_US', 'Runs small');
        /** @var EditorPresetSaverInterface $saver */
        $saver = self::getContainer()->get(EditorPresetSaverInterface::class);
        $saver->save(new Preset('Size note', [$note, new StoredBlock('0193a5be-0000-7000-8000-000000000012', 'retired_banner', 1)], 'Products'));
        $about = self::createPage('About us', 'about-us');
        $this->signInAsAdmin();

        $page = $this->visit(sprintf('/admin/cms/entry/%s/builder', $about->getUuid()));
        $page->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $picker = $page->getByRole('dialog', ['name' => 'Add block']);
        $picker->getByRole('tab', ['name' => 'Presets'])->click();
        $leftOut = $picker->getByRole('list', ['name' => 'Not available here']);
        Expect::locator($leftOut)->toContainText('Size note');
        Expect::locator($leftOut->getByRole('list', ['name' => 'Invalid blocks']))->toContainText('retired_banner');
        Expect::locator($leftOut->getByRole('link', ['name' => 'Download Size note']))->toBeVisible();

        $leftOut->getByRole('button', ['name' => 'Delete Size note'])->click();
        $leftOut->getByRole('button', ['name' => 'Delete', 'exact' => true])->click();

        Expect::locator($picker->getByText('Size note'))->toHaveCount(0);
    }

    /**
     * The editor preset with the name, as the database has it. The kernel serves the browser's requests too, so its
     * entity manager isn't cleared, but each preset is read again
     */
    private static function editorPreset(string $name): Preset
    {
        /** @var EditorPresetRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.editor_preset');
        foreach ($repository->findAllInCreationOrder() as $row) {
            self::entityManager()->refresh($row);
            $preset = (new EditorPresetContent())->read($row);
            if ($name === $preset->name) {
                return $preset;
            }
        }

        throw new \LogicException(sprintf('There is no editor preset named "%s"', $name));
    }
}

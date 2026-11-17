<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;

/**
 * A project's own field type is entered with its own field control, a custom element the builder loads from
 * setono_sylius_cms.builder.scripts: the test application's opening hours, whose control picks a photo through
 * .builder.pickMedia(). See "Field controls" in docs/spec/extending.md
 */
final class FieldControlTest extends UITestCase
{
    use MediaItems;

    /**
     * @test
     */
    public function it_lets_an_editor_enter_opening_hours_and_pick_a_photo_through_the_field_types_own_field_control(): void
    {
        $this->signInAsAdmin();
        $this->uploadMediaItem('shopfront.png', self::png(80, 40));
        $page = self::createPage('Visit us', 'visit-us');

        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $browser->getByRole('button', ['name' => 'Add block', 'exact' => true])->click();
        $browser->getByRole('dialog', ['name' => 'Add block'])->getByRole('button', ['name' => 'Store hours', 'exact' => true])->click();

        // The custom element, as a group the field's label names
        $control = $browser->getByRole('group', ['name' => 'Opening hours']);
        $monday = $control->getByRole('row', ['name' => 'Monday', 'exact' => false]);
        $monday->getByLabel('Opens')->fill('09:00');
        $monday->getByLabel('Closes')->fill('17:00');
        $saturday = $control->getByRole('row', ['name' => 'Saturday', 'exact' => false]);
        $saturday->getByLabel('Opens')->fill('10:00');
        $saturday->getByLabel('Closes')->fill('14:00');

        // Its photo button opens the builder's media picker through .builder.pickMedia(), with the builder's own words
        $control->getByRole('button', ['name' => 'Photo: Choose from the library'])->click();
        $picker = $browser->getByRole('dialog', ['name' => 'Choose from the media library']);
        $picker->locator('[data-media-item]')->filter(['hasText' => 'shopfront'])->click();
        $picker->getByRole('button', ['name' => 'Choose', 'exact' => true])->click();

        Expect::locator($picker)->toHaveCount(0);
        Expect::locator($control->getByRole('button', ['name' => 'Photo: Replace']))->toBeVisible();
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');

        $preview = $browser->frameLocator('iframe');
        self::assertTexts(['Monday 09:00–17:00', 'Saturday 10:00–14:00'], $preview->locator('.cms-tree > .app-store-hours li'));
        Expect::locator($preview->locator('.cms-tree > .app-store-hours .app-store-hours__photo'))->toHaveText('shopfront');

        // What it saved is what the builder shows when it opens again
        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        $browser->getByRole('navigation', ['name' => 'Blocks'])->getByRole('button', ['name' => 'Store hours', 'exact' => false])->first()->click();
        $control = $browser->getByRole('group', ['name' => 'Opening hours']);
        Expect::locator($control->getByRole('row', ['name' => 'Monday', 'exact' => false])->getByLabel('Opens'))->toHaveValue('09:00');
        Expect::locator($control->getByRole('row', ['name' => 'Saturday', 'exact' => false])->getByLabel('Closes'))->toHaveValue('14:00');
        Expect::locator($control->getByRole('button', ['name' => 'Photo: Replace']))->toBeVisible();
    }
}

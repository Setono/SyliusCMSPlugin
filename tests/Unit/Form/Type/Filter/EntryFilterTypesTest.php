<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Form\Type\Filter;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\EntryStatusFilter;
use Setono\SyliusCMSPlugin\Form\Type\Filter\EntryChannelFilterType;
use Setono\SyliusCMSPlugin\Form\Type\Filter\EntryStatusFilterType;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * The form types of the entry lists' grid filters for status and channel. Sylius's grids render them in their filter
 * form and hand the filters the submitted values
 */
final class EntryFilterTypesTest extends TypeTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_offers_the_statuses_with_labels_and_any_status_in_the_status_filter(): void
    {
        $form = $this->factory->createNamed('status', EntryStatusFilterType::class);
        $view = $form->createView();

        /** @var array{placeholder: string, required: bool, choices: list<ChoiceView>, block_prefixes: list<string>} $vars */
        $vars = $view->vars;
        self::assertSame('setono_sylius_cms.ui.any_status', $vars['placeholder']);
        self::assertFalse($vars['required']);
        self::assertContains('setono_sylius_cms_entry_status_filter', $vars['block_prefixes']);
        self::assertSame(
            [
                'needs_attention' => 'setono_sylius_cms.ui.status_filter.needs_attention',
                'unpublished_changes' => 'setono_sylius_cms.ui.status_filter.unpublished_changes',
                'never_published' => 'setono_sylius_cms.ui.status_filter.never_published',
                'scheduled' => 'setono_sylius_cms.ui.status_filter.scheduled',
                'disabled_or_outside_date_window' => 'setono_sylius_cms.ui.status_filter.disabled_or_outside_date_window',
            ],
            self::labels($vars['choices']),
        );

        $form->submit('never_published');
        self::assertTrue($form->isValid());
        self::assertSame(EntryStatusFilter::NeverPublished, $form->getData());
    }

    /**
     * @test
     */
    public function it_refuses_a_status_the_status_filter_does_not_offer(): void
    {
        $form = $this->factory->createNamed('status', EntryStatusFilterType::class);

        $form->submit('deleted');

        self::assertFalse($form->isValid());
    }

    /**
     * @test
     */
    public function it_offers_the_channels_by_name_and_any_channel_in_the_channel_filter(): void
    {
        $form = $this->factory->createNamed('channel', EntryChannelFilterType::class);
        $view = $form->createView();

        /** @var array{placeholder: string, required: bool, choices: list<ChoiceView>, block_prefixes: list<string>} $vars */
        $vars = $view->vars;
        self::assertSame('setono_sylius_cms.ui.any_channel', $vars['placeholder']);
        self::assertFalse($vars['required']);
        self::assertContains('setono_sylius_cms_entry_channel_filter', $vars['block_prefixes']);
        self::assertSame(['B2B' => 'Wholesale', 'MOBILE' => 'MOBILE', 'WEB' => 'Web store'], self::labels($vars['choices']));

        $form->submit('B2B');
        self::assertTrue($form->isValid());
        self::assertSame('B2B', $form->getData());
    }

    /**
     * @test
     */
    public function it_refuses_a_channel_the_channel_filter_does_not_offer(): void
    {
        $form = $this->factory->createNamed('channel', EntryChannelFilterType::class);

        $form->submit('POS');

        self::assertFalse($form->isValid());
    }

    /**
     * @return list<PreloadedExtension>
     */
    protected function getExtensions(): array
    {
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        // A channel without a name is offered by its code, and one without a code isn't offered
        $channels->findBy([], ['name' => 'ASC'])->willReturn([self::channel('B2B', 'Wholesale'), self::channel('MOBILE', null), self::channel(null, 'Unfinished'), self::channel('WEB', 'Web store')]);

        return [new PreloadedExtension([new EntryStatusFilterType(), new EntryChannelFilterType($channels->reveal())], [])];
    }

    /**
     * @param list<ChoiceView> $choices
     *
     * @return array<string, string> value => label
     */
    private static function labels(array $choices): array
    {
        $labels = [];
        foreach ($choices as $choice) {
            self::assertIsString($choice->value);
            self::assertIsString($choice->label);
            $labels[$choice->value] = $choice->label;
        }

        return $labels;
    }

    private static function channel(?string $code, ?string $name): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);

        return $channel;
    }
}

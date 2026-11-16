<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\EntryListCriteria;
use Setono\SyliusCMSPlugin\Admin\EntryStatusFilter;
use Setono\SyliusCMSPlugin\Form\Type\EntryListFilterType;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class EntryListFilterTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_submits_the_criteria_of_a_list(): void
    {
        $form = $this->factory->createNamed('criteria', EntryListFilterType::class);

        $form->submit(['search' => ' About ', 'status' => 'never_published', 'channel' => 'B2B']);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertEquals(new EntryListCriteria('About', EntryStatusFilter::NeverPublished, 'B2B'), $form->getData());
    }

    /**
     * @test
     */
    public function it_lets_every_entry_through_when_nothing_is_filtered(): void
    {
        $form = $this->factory->createNamed('criteria', EntryListFilterType::class);

        $form->submit([]);

        self::assertTrue($form->isValid());
        self::assertEquals(new EntryListCriteria(), $form->getData());
    }

    /**
     * @test
     */
    public function it_refuses_a_status_or_channel_it_does_not_offer(): void
    {
        $status = $this->factory->createNamed('criteria', EntryListFilterType::class);
        $status->submit(['status' => 'deleted']);

        $channel = $this->factory->createNamed('criteria', EntryListFilterType::class);
        $channel->submit(['channel' => 'MOBILE']);

        self::assertFalse($status->isValid());
        self::assertFalse($channel->isValid());
    }

    /**
     * @test
     */
    public function it_is_sent_with_get_so_a_filtered_list_has_its_own_url(): void
    {
        $form = $this->factory->createNamed('criteria', EntryListFilterType::class);

        self::assertSame('GET', $form->getConfig()->getMethod());
        self::assertFalse($form->getConfig()->getOption('csrf_protection'));
    }

    /**
     * @test
     */
    public function it_offers_the_statuses_and_the_channels_by_name_with_labels(): void
    {
        $view = $this->factory->createNamed('criteria', EntryListFilterType::class, new EntryListCriteria('About', EntryStatusFilter::UnpublishedChanges, 'WEB'))->createView();

        /** @var array{label: string, value: string} $search */
        $search = $view['search']->vars;
        self::assertSame('setono_sylius_cms.ui.search', $search['label']);
        self::assertSame('About', $search['value']);

        /** @var array{label: string, placeholder: string, value: string, choices: list<ChoiceView>} $status */
        $status = $view['status']->vars;
        self::assertSame('setono_sylius_cms.ui.status', $status['label']);
        self::assertSame('setono_sylius_cms.ui.any_status', $status['placeholder']);
        self::assertSame('unpublished_changes', $status['value']);
        self::assertSame(
            [
                'unpublished_changes' => 'setono_sylius_cms.ui.status_filter.unpublished_changes',
                'never_published' => 'setono_sylius_cms.ui.status_filter.never_published',
                'disabled_or_outside_date_window' => 'setono_sylius_cms.ui.status_filter.disabled_or_outside_date_window',
            ],
            self::labels($status['choices']),
        );

        /** @var array{label: string, placeholder: string, value: string, choices: list<ChoiceView>} $channel */
        $channel = $view['channel']->vars;
        self::assertSame('setono_sylius_cms.ui.channel', $channel['label']);
        self::assertSame('setono_sylius_cms.ui.any_channel', $channel['placeholder']);
        self::assertSame('WEB', $channel['value']);
        self::assertSame(['B2B' => 'Wholesale', 'WEB' => 'Web store'], self::labels($channel['choices']));
    }

    /**
     * @return list<PreloadedExtension>
     */
    protected function getExtensions(): array
    {
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy([], ['name' => 'ASC'])->willReturn([self::channel('B2B', 'Wholesale'), self::channel('WEB', 'Web store')]);

        return [new PreloadedExtension([new EntryListFilterType($channels->reveal())], [])];
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
            $labels[$choice->value] = (string) $choice->label;
        }

        return $labels;
    }

    private static function channel(string $code, string $name): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);

        return $channel;
    }
}

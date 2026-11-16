<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Admin\EntryListCriteria;
use Setono\SyliusCMSPlugin\Admin\EntryStatusFilter;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<EntryListCriteria>
 *
 * The filters of an entry list: search, status and channel. It's sent with GET, so a filtered list has a URL of its
 * own, and its data is the list's EntryListCriteria. See "Entry lists" in docs/spec/admin.md
 */
final class EntryListFilterType extends AbstractType
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(private readonly ChannelRepositoryInterface $channelRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', TextType::class, [
                'label' => 'setono_sylius_cms.ui.search',
                'required' => false,
            ])
            ->add('status', EnumType::class, [
                'label' => 'setono_sylius_cms.ui.status',
                'class' => EntryStatusFilter::class,
                'choice_label' => static fn (EntryStatusFilter $status): string => $status->label(),
                'placeholder' => 'setono_sylius_cms.ui.any_status',
                'required' => false,
            ])
            ->add('channel', ChoiceType::class, [
                'label' => 'setono_sylius_cms.ui.channel',
                'choices' => $this->channels(),
                'placeholder' => 'setono_sylius_cms.ui.any_channel',
                'required' => false,
            ])
        ;

        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?EntryListCriteria $criteria): array => ['search' => $criteria?->search, 'status' => $criteria?->status, 'channel' => $criteria?->channel],
            self::criteria(...),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    /**
     * @param array{search: ?string, status: ?EntryStatusFilter, channel: ?string} $data what the fields submitted
     */
    private static function criteria(array $data): EntryListCriteria
    {
        return new EntryListCriteria($data['search'], $data['status'], $data['channel']);
    }

    /**
     * @return array<string, string> the channels' names => their codes, by name
     */
    private function channels(): array
    {
        $channels = [];
        foreach ($this->channelRepository->findBy([], ['name' => 'ASC']) as $channel) {
            $channels[(string) $channel->getName()] = (string) $channel->getCode();
        }

        return $channels;
    }
}

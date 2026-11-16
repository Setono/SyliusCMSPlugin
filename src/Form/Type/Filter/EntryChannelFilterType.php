<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type\Filter;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The form of the entry lists' channel filter (the grid filter type setono_sylius_cms_entry_channel): the store's
 * channels by name, whose values are their codes. See "Entry lists" in docs/spec/admin.md
 *
 * @extends AbstractType<string|null>
 */
final class EntryChannelFilterType extends AbstractType
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(private readonly ChannelRepositoryInterface $channelRepository)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => fn (Options $options): array => $this->channels(),
            'placeholder' => 'setono_sylius_cms.ui.any_channel',
            'required' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_entry_channel_filter';
    }

    /**
     * @return array<string, string> the channels' names, or else their codes => their codes, by name
     */
    private function channels(): array
    {
        $channels = [];
        foreach ($this->channelRepository->findBy([], ['name' => 'ASC']) as $channel) {
            $code = $channel->getCode();
            if (null === $code) {
                continue;
            }

            $channels[$channel->getName() ?? $code] = $code;
        }

        return $channels;
    }
}

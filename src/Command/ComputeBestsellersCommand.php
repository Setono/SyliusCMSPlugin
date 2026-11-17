<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Command;

use Setono\SyliusCMSPlugin\Bestsellers\BestsellersComputer;
use Setono\SyliusCMSPlugin\Event\BestsellersComputed;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Recomputes the bestseller scores: runs the bestsellers calculator for every channel, or the one --channel names, and
 * replaces each channel's scores in the plugin's table, which the Bestsellers source lists products by. Once every
 * channel is committed, it dispatches BestsellersComputed with their codes. A project runs it from cron.
 * See "Bestsellers" in docs/spec/sources.md and "Console commands" in docs/spec/extending.md
 */
#[AsCommand(name: 'setono:cms:bestsellers:compute', description: 'Recomputes the bestseller scores, for every channel by default')]
final class ComputeBestsellersCommand extends Command
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channels
     */
    public function __construct(
        private readonly ChannelRepositoryInterface $channels,
        private readonly BestsellersComputer $computer,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('channel', null, InputOption::VALUE_REQUIRED, 'The code of the one channel to compute');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $channels = $this->channelsToCompute($input);
        if (null === $channels) {
            $io->error(sprintf('The store has no channel with the code "%s".', self::stringOption($input, 'channel')));

            return Command::FAILURE;
        }

        $channelCodes = [];
        foreach ($channels as $channel) {
            $scored = $this->computer->compute($channel);
            $channelCodes[] = $channelCode = (string) $channel->getCode();
            $io->writeln(sprintf('%s: scored %d product%s', $channelCode, $scored, 1 === $scored ? '' : 's'));
        }

        $this->eventDispatcher->dispatch(new BestsellersComputed($channelCodes));

        $io->success(sprintf('Computed the bestsellers of %d channel%s.', count($channelCodes), 1 === count($channelCodes) ? '' : 's'));

        return Command::SUCCESS;
    }

    /**
     * @return list<ChannelInterface>|null every channel, or the one --channel names, or null when the store has no
     *                                     channel with that code
     */
    private function channelsToCompute(InputInterface $input): ?array
    {
        $code = self::stringOption($input, 'channel');
        if (null === $code) {
            $channels = [];
            foreach ($this->channels->findAll() as $channel) {
                $channels[] = $channel;
            }

            return $channels;
        }

        $channel = $this->channels->findOneByCode($code);

        return $channel instanceof ChannelInterface ? [$channel] : null;
    }

    private static function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) ? $value : null;
    }
}

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisherInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntryException;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishVetoedException;
use Setono\SyliusCMSPlugin\Publishing\ScheduledPublishDue;
use Setono\SyliusCMSPlugin\Publishing\ScheduledPublishDueHandler;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ScheduledPublishDueHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const TOKEN = '5f0c8c0e-6b1a-4c2e-9d3f-7a8b9c0d1e2f';

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    /** @var ObjectProphecy<EntryPublisherInterface> */
    private ObjectProphecy $publisher;

    /** @var ObjectProphecy<PublishSchedulerInterface> */
    private ObjectProphecy $scheduler;

    /** @var ObjectProphecy<TranslatorInterface> */
    private ObjectProphecy $translator;

    private Page $page;

    protected function setUp(): void
    {
        $this->page = new Page();
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
        $this->entries->findOneByUuid($this->page->getUuid())->willReturn($this->page);
        $this->publisher = $this->prophesize(EntryPublisherInterface::class);
        $this->scheduler = $this->prophesize(PublishSchedulerInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
    }

    /**
     * @test
     */
    public function it_runs_the_scheduled_publish_the_message_is_for(): void
    {
        $this->publisher->publishScheduled($this->page, self::TOKEN)->willReturn(true)->shouldBeCalledOnce();
        $this->scheduler->fail(Argument::cetera())->shouldNotBeCalled();

        $this->handle();
    }

    /**
     * @test
     */
    public function a_stale_message_is_ignored(): void
    {
        $this->publisher->publishScheduled($this->page, self::TOKEN)->willReturn(false)->shouldBeCalledOnce();
        $this->scheduler->fail(Argument::cetera())->shouldNotBeCalled();

        $this->handle();
    }

    /**
     * @test
     */
    public function a_message_for_an_entry_deleted_since_does_nothing(): void
    {
        $this->entries->findOneByUuid($this->page->getUuid())->willReturn(null);
        $this->publisher->publishScheduled(Argument::cetera())->shouldNotBeCalled();

        $this->handle();
    }

    /**
     * @test
     */
    public function a_veto_flags_the_scheduled_publish_as_failed_with_its_message_instead_of_retrying(): void
    {
        $this->publisher->publishScheduled($this->page, self::TOKEN)->willThrow(new PublishVetoedException('Not on a Sunday'));
        $this->scheduler->fail($this->page, self::TOKEN, 'Not on a Sunday')->shouldBeCalledOnce();

        $this->handle();
    }

    /**
     * @test
     */
    public function validation_errors_flag_the_scheduled_publish_as_failed_with_what_they_say(): void
    {
        $this->publisher->publishScheduled($this->page, self::TOKEN)->willThrow(new InvalidEntryException([
            new ValidationError(null, 'slug', 'en_US', 'Another entry has this URL.'),
            new ValidationError('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'text', 'en_US', 'This value should not be blank.'),
        ]));
        $this->translator->trans('setono_sylius_cms.scheduled_publish.invalid', [
            '%count%' => 2,
            '%errors%' => 'Another entry has this URL. This value should not be blank.',
        ])->willReturn('2 errors need fixing');
        $this->scheduler->fail($this->page, self::TOKEN, '2 errors need fixing')->shouldBeCalledOnce();

        $this->handle();
    }

    private function handle(): void
    {
        $handler = new ScheduledPublishDueHandler($this->entries->reveal(), $this->publisher->reveal(), $this->scheduler->reveal(), $this->translator->reveal());

        $handler(new ScheduledPublishDue($this->page->getUuid(), self::TOKEN));
    }
}

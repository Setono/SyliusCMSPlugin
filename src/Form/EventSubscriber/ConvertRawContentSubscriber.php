<?php
declare(strict_types=1);


namespace Setono\SyliusCMSPlugin\Form\EventSubscriber;


use Setono\EditorJS\Parser\ParserInterface;
use Setono\EditorJS\Renderer\RendererInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\FormEvents;

final class ConvertRawContentSubscriber implements EventSubscriberInterface
{
    private ParserInterface $parser;
    private RendererInterface $renderer;

    public function __construct(ParserInterface $parser, RendererInterface $renderer)
    {
        $this->parser = $parser;
        $this->renderer = $renderer;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'parse'
        ];
    }

    public function parse(PreSubmitEvent $event): void
    {
        $data = $event->getData();
        if(!isset($data['content'], $data['rawContent']) || '' === $data['rawContent']) {
            return;
        }

        $data['content'] = $this->renderer->render($this->parser->parse($data['rawContent']));

        $event->setData($data);
    }
}

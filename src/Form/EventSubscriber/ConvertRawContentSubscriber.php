<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\EventSubscriber;

use Setono\EditorJS\Exception\ParserException;
use Setono\EditorJS\Exception\RendererException;
use Setono\EditorJS\Parser\ParserInterface;
use Setono\EditorJS\Renderer\RendererInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Webmozart\Assert\Assert;

final class ConvertRawContentSubscriber implements EventSubscriberInterface
{
    private ParserInterface $parser;

    private RendererInterface $renderer;

    private string $sourceProperty;

    private string $targetProperty;

    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        ParserInterface $parser,
        RendererInterface $renderer,
        string $sourceProperty = 'rawContent',
        string $targetProperty = 'content',
        PropertyAccessorInterface $propertyAccessor = null
    ) {
        $this->parser = $parser;
        $this->renderer = $renderer;
        // we work on array so we use index notation
        // see https://symfony.com/doc/current/components/property_access.html#reading-from-arrays
        $this->sourceProperty = sprintf('[%s]', $sourceProperty);
        $this->targetProperty = sprintf('[%s]', $targetProperty);
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'convert'
        ];
    }

    public function convert(FormEvent $event): void
    {
        $data = $event->getData();
        if (!is_array($data)) {
            return;
        }

        if (!$this->propertyAccessor->isReadable($data, $this->sourceProperty)) {
            return;
        }

        if (!$this->propertyAccessor->isWritable($data, $this->targetProperty)) {
            return;
        }

        $rawContent = $this->propertyAccessor->getValue($data, $this->sourceProperty);
        if (null === $rawContent || '' === $rawContent) {
            $this->propertyAccessor->setValue($data, $this->targetProperty, $rawContent);

            return;
        }

        Assert::string($rawContent);

        try {
            $html = $this->renderer->render($this->parser->parse($rawContent));
        } catch (ParserException $e) {
            throw new TransformationFailedException('Transforming EditorJS JSON into HTML failed', 0, $e, $e->getMessage());
        } catch (RendererException $e) {
            throw new TransformationFailedException('Rendering parsing result failed', 0, $e, $e->getMessage());
        } catch (\Throwable $e) {
            throw new TransformationFailedException('Something went wrong trying to either parse or render the EditorJS content', 0, $e, 'Something went wrong trying to either parse or render the EditorJS content. ' . $e->getMessage());
        }

        $this->propertyAccessor->setValue($data, $this->targetProperty, $html);

        $event->setData($data);
    }
}

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class SetQueryParameterValueOnObjectSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;

    private string $queryParameter;

    private string $targetProperty;

    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        RequestStack $requestStack,
        string $queryParameter = 'code',
        string $targetProperty = 'code',
        PropertyAccessorInterface $propertyAccessor = null,
    ) {
        $this->requestStack = $requestStack;
        $this->queryParameter = $queryParameter;
        $this->targetProperty = $targetProperty;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'set',
        ];
    }

    public function set(FormEvent $event): void
    {
        $data = $event->getData();
        if (!is_object($data)) {
            return;
        }

        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return;
        }

        $value = $request->query->get($this->queryParameter);
        if (!is_string($value)) {
            return;
        }

        if (!$this->propertyAccessor->isReadable($data, $this->targetProperty)) {
            return;
        }

        if (!$this->propertyAccessor->isWritable($data, $this->targetProperty)) {
            return;
        }

        $existingValue = $this->propertyAccessor->getValue($data, $this->targetProperty);
        if ($existingValue !== null && $existingValue !== '') {
            return;
        }

        $this->propertyAccessor->setValue($data, $this->targetProperty, $value);
    }
}

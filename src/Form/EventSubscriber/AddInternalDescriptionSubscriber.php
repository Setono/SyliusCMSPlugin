<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormTypeInterface;

final class AddInternalDescriptionSubscriber implements EventSubscriberInterface
{
    /** @var class-string<FormTypeInterface> */
    private string $type;

    private array $options;

    /**
     * @param class-string<FormTypeInterface> $type
     */
    public function __construct(string $type = TextareaType::class, array $options = [])
    {
        $this->type = $type;
        $this->options = $options;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'add',
        ];
    }

    public function add(FormEvent $event): void
    {
        $form = $event->getForm();
        $form->add('internalDescription', $this->type, array_merge([
            'label' => 'setono_sylius_cms.form.internal_description',
            'required' => false,
            'attr' => [
                'placeholder' => 'setono_sylius_cms.form.internal_description_placeholder',
                'rows' => 2,
            ],
        ], $this->options));
    }
}

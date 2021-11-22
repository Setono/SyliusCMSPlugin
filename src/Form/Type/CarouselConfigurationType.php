<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class CarouselConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            dump($event->getData());
        });
        $builder->add('infinite', CheckboxType::class, [
            'required' => false,
        ]);
        $builder->add('slidesToShow', IntegerType::class, [
            'empty_data' => 1,
        ]);
        $builder->add('slidesToScroll', IntegerType::class, [
            'empty_data' => 1,
        ]);
    }
}

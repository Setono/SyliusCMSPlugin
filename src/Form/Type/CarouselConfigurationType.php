<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

final class CarouselConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('infinite', CheckboxType::class, [
            'required' => false,
            'label' => 'setono_sylius_cms.form.carousel.configuration.infinite_scroll',
        ]);
        $builder->add('slidesToShow', IntegerType::class, [
            'empty_data' => 1,
            'label' => 'setono_sylius_cms.form.carousel.configuration.slides_to_show',
        ]);
        $builder->add('slidesToScroll', IntegerType::class, [
            'empty_data' => 1,
            'label' => 'setono_sylius_cms.form.carousel.configuration.slides_to_scroll',
        ]);
        $builder->add('autoplay', CheckboxType::class, [
            'required' => false,
            'label' => 'setono_sylius_cms.form.carousel.configuration.autoplay',
        ]);
        $builder->add('autoplaySpeed', IntegerType::class, [
            'empty_data' => 1,
            'label' => 'setono_sylius_cms.form.carousel.configuration.autoplay_speed',
        ]);
    }
}

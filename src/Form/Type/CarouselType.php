<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Form\EventSubscriber\AddInternalDescriptionSubscriber;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

final class CarouselType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('slides', CollectionType::class, [
                'label' => 'setono_sylius_cms.form.carousel.slides',
                'allow_add' => false,
                'allow_delete' => true,
                'by_reference' => false,
                'entry_type' => SlideType::class,
            ])
            ->add('configuration', CarouselConfigurationType::class, [
                'label' => 'setono_sylius_cms.form.carousel.configuration',
            ])
            ->addEventSubscriber(new AddCodeFormSubscriber())
            ->addEventSubscriber(new AddInternalDescriptionSubscriber())
        ;
    }
}

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

final class CarouselType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new AddCodeFormSubscriber());
        $builder->add('carouselBlocks', CollectionType::class, [
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'entry_type' => CarouselBlockType::class,
        ]);
        $builder->add('configuration', CarouselConfigurationType::class, [

        ]);
    }
}

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Form\EventSubscriber\AddInternalDescriptionSubscriber;
use Setono\SyliusCMSPlugin\Form\EventSubscriber\SetQueryParameterValueOnObjectSubscriber;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class CarouselType extends AbstractResourceType
{
    private RequestStack $requestStack;

    /**
     * @param array<array-key, string> $validationGroups
     */
    public function __construct(
        RequestStack $requestStack,
        string $dataClass,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);

        $this->requestStack = $requestStack;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('carouselBlocks', CollectionType::class, [
                'label' => 'setono_sylius_cms.form.carousel.blocks',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'entry_type' => CarouselBlockType::class,
            ])
            ->add('configuration', CarouselConfigurationType::class, [
                'label' => 'setono_sylius_cms.form.carousel.configuration',
            ])
            ->addEventSubscriber(new SetQueryParameterValueOnObjectSubscriber($this->requestStack))
            ->addEventSubscriber(new AddCodeFormSubscriber())
            ->addEventSubscriber(new AddInternalDescriptionSubscriber())
        ;
    }
}

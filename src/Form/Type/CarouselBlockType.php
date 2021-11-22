<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\FormBuilderInterface;

final class CarouselBlockType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('block', BlockAutocompleteChoiceType::class);
    }
}

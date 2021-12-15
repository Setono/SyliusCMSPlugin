<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

final class ViewBlockType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('block', BlockAutocompleteChoiceType::class, [
                'label' => false,
                'placeholder' => 'setono_sylius_cms.form.view.block_placeholder',
            ])
            ->add('position', IntegerType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'setono_sylius_cms.form.view.position_placeholder',
                ],
            ])
        ;
    }
}

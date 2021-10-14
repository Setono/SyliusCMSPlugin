<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ViewSectionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var string $sectionName */
        foreach ($options['sections'] as $sectionName) {
            $builder->add($sectionName, ViewSectionType::class);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('sections', []);
    }
}

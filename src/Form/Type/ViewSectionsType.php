<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ViewSectionsType extends AbstractType
{
    /**
     * @param array{sections: array<array-key, string>} $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ($options['sections'] as $sectionName) {
            $humanizedSectionName = ucfirst(substr($sectionName, strlen('section_')));
            $builder->add($sectionName, ViewSectionType::class, [
                'label' => 'setono_sylius_cms.form.view.section_blocks',
                'label_translation_parameters' => [
                    '%section%' => $humanizedSectionName,
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('sections', []);
    }
}

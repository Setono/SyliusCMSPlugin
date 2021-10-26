<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

final class ViewSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('blocks', CollectionType::class, [
            'entry_type' => BlockAutocompleteChoiceType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'entry_options' => [
                'label' => false,
            ],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_view_section';
    }
}

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type\CMSView;

use Setono\SyliusCMSPlugin\Form\Type\BlockAutocompleteChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @internal */
final class CMSBlockModelType extends AbstractType
{
    private string $blockModelClass;

    public function __construct(string $blockModelClass)
    {
        $this->blockModelClass = $blockModelClass;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('block', BlockAutocompleteChoiceType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', $this->blockModelClass);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_model_block';
    }
}

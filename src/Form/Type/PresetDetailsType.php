<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Controller\Admin\PresetsScreenRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An editor preset's Edit form on the Presets screen: its name, category and description. Preset\PresetDetails checks
 * the values, as it does the builder's, so the form only reads them. Each preset's form is named after it
 * (PresetsScreenRequest::formName()), as the screen has one per row. See "Presets" in docs/spec/admin.md
 *
 * @extends AbstractType<array{name: string|null, category: string|null, description: string|null}>
 */
final class PresetDetailsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'setono_sylius_cms.ui.preset_name',
                'empty_data' => '',
            ])
            ->add('category', TextType::class, [
                'label' => 'setono_sylius_cms.ui.preset_category',
                'required' => false,
                // The categories in use, which the screen lists once
                'attr' => ['list' => 'cms-preset-categories'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'setono_sylius_cms.ui.preset_description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => PresetsScreenRequest::CSRF_TOKEN_ID,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_preset_details';
    }
}

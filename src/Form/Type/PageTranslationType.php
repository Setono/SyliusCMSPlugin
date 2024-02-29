<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class PageTranslationType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('slug', TextType::class, [
                'label' => 'setono_sylius_cms.form.page.slug',
            ])
            ->add('title', TextType::class, [
                'label' => 'setono_sylius_cms.form.page.title',
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'setono_sylius_cms.form.page.meta_description',
            ])
            ->add('content', EditorJSType::class, [
                'label' => 'setono_sylius_cms.form.page.content',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_page_translation';
    }
}

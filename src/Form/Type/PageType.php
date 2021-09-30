<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;

final class PageType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'setono_sylius_cms.form.page.enabled',
                'required' => false,
            ])
            ->add('enabledFrom', DateTimeType::class, [
                'label' => 'setono_sylius_cms.form.page.enabled_from',
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'required' => false,
            ])
            ->add('enabledUntil', DateTimeType::class, [
                'label' => 'setono_sylius_cms.form.page.enabled_until',
                'date_widget' => 'single_text',
                'time_widget' => 'single_text',
                'required' => false,
            ])
            ->add('channels', ChannelChoiceType::class, [
                'multiple' => true,
                'expanded' => true,
                'label' => 'sylius.ui.channels',
                'required' => false,
            ])
            ->add('view', ViewChoiceType::class, [
                'label' => 'setono_sylius_cms.form.page.view',
            ])
            ->add('translations', ResourceTranslationsType::class, [
                'entry_type' => PageTranslationType::class,
                'label' => 'setono_sylius_cms.form.page.translations',
                'required' => false,
            ])->addEventSubscriber(new AddCodeFormSubscriber())
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_page';
    }
}

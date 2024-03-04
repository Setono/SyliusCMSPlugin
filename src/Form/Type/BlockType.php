<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Form\EventSubscriber\AddInternalDescriptionSubscriber;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceTranslationsType;
use Symfony\Component\Form\FormBuilderInterface;

final class BlockType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('defaultContent', EditorJSType::class, [
                'label' => 'setono_sylius_cms.form.block.default_content',
            ])
            ->add('translations', ResourceTranslationsType::class, [
                'entry_type' => BlockTranslationType::class,
                'label' => 'setono_sylius_cms.form.block.translations',
            ])
            ->addEventSubscriber(new AddCodeFormSubscriber())
            ->addEventSubscriber(new AddInternalDescriptionSubscriber())
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_block';
    }
}

<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

final class BlockTranslationType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'setono_sylius_cms.form.block.content',
            ])
            ->add('rawContent', TextareaType::class, [
                'label' => 'setono_sylius_cms.form.block.raw_content',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_block_translation';
    }
}

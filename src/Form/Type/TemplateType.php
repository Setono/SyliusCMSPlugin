<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

final class TemplateType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('source', HiddenType::class, [
                'label' => 'setono_sylius_cms.form.template.source',
            ])
            ->add('internalDescription', TextareaType::class, [
                'label' => 'setono_sylius_cms.form.internal_description',
                'required' => false,
            ])
            ->addEventSubscriber(new AddCodeFormSubscriber())
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_template';
    }
}

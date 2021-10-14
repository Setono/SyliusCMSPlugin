<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Template\RegistryInterface;
use Setono\SyliusCMSPlugin\Template\Template;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TemplateChoiceType extends AbstractType
{
    private RegistryInterface $templateRegistry;

    public function __construct(RegistryInterface $templateRegistry)
    {
        $this->templateRegistry = $templateRegistry;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->addModelTransformer(new CallbackTransformer(
            function (?string $code): ?Template {
                if (null === $code) {
                    return null;
                }

                if (!$this->templateRegistry->has($code)) {
                    return null;
                }

                return $this->templateRegistry->get($code);
            },
            function (?Template $template): ?string {
                if (null === $template) {
                    return null;
                }

                return $template->getCode();
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'setono_sylius_cms.form.view.template',
            'placeholder' => 'setono_sylius_cms.form.view.template_placeholder',
            'choices' => $this->templateRegistry->all(),
            'choice_value' => 'code',
            'choice_label' => 'label',
            'choice_translation_domain' => false,
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_template_choice';
    }
}

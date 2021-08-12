<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Form\EventSubscriber\SetQueryParameterValueOnObjectSubscriber;
use Setono\SyliusCMSPlugin\Template\RegistryInterface;
use Setono\SyliusCMSPlugin\Template\Template;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ViewType extends AbstractResourceType
{
    private RegistryInterface $templateRegistry;

    private RequestStack $requestStack;

    /**
     * @param array<array-key, string> $validationGroups
     */
    public function __construct(
        RegistryInterface $templateRegistry,
        RequestStack $requestStack,
        string $dataClass,
        array $validationGroups = []
    ) {
        parent::__construct($dataClass, $validationGroups);

        $this->templateRegistry = $templateRegistry;
        $this->requestStack = $requestStack;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'setono_sylius_cms.form.view.code',
            ])
            ->add('template', ChoiceType::class, [
                'label' => 'setono_sylius_cms.form.view.template',
                'placeholder' => 'setono_sylius_cms.form.view.template_placeholder',
                'choices' => $this->templateRegistry->all(),
                'choice_value' => 'code',
                'choice_label' => 'label'
            ])
            ->addEventSubscriber(new SetQueryParameterValueOnObjectSubscriber($this->requestStack))
        ;

        $builder->get('template')->addModelTransformer(new CallbackTransformer(
            function (?string $code) {
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

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_view';
    }
}

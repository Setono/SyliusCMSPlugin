<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Form\EventSubscriber\AddInternalDescriptionSubscriber;
use Setono\SyliusCMSPlugin\Form\EventSubscriber\SetQueryParameterValueOnObjectSubscriber;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Template\RegistryInterface;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Webmozart\Assert\Assert;

final class ViewType extends AbstractResourceType
{
    private RequestStack $requestStack;

    private DataMapperInterface $viewSectionsDataMapper;

    private RegistryInterface $templateRegistry;

    /**
     * @param array<array-key, string> $validationGroups
     */
    public function __construct(
        RequestStack $requestStack,
        DataMapperInterface $viewSectionsDataMapper,
        RegistryInterface $templateRegistry,
        string $dataClass,
        array $validationGroups = [],
    ) {
        parent::__construct($dataClass, $validationGroups);

        $this->requestStack = $requestStack;
        $this->viewSectionsDataMapper = $viewSectionsDataMapper;
        $this->templateRegistry = $templateRegistry;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('template', TemplateChoiceType::class);
        $builder->addEventSubscriber(new SetQueryParameterValueOnObjectSubscriber($this->requestStack));
        $builder->addEventSubscriber(new AddCodeFormSubscriber());
        $builder->addEventSubscriber(new AddInternalDescriptionSubscriber());
        $builder->setDataMapper($this->viewSectionsDataMapper);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var ViewInterface|null $view */
            $view = $event->getData();
            if (null === $view) {
                $templateName = null;
            } else {
                $templateName = $view->getTemplate();
            }

            $form = $event->getForm();
            $this->addViewSectionsFromTemplateName($templateName, $form);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            Assert::isArray($data);
            Assert::keyExists($data, 'template');
            $templateName = $data['template'];
            if (null === $templateName) {
                return;
            }
            Assert::string($templateName);

            $form = $event->getForm();
            $this->addViewSectionsFromTemplateName($templateName, $form);
        });

        $templates = $this->templateRegistry->all();

        $sectionsPrototypes = [];
        foreach ($templates as $template) {
            $sectionsPrototypes[$template->getCode()] = $builder->create('sections', ViewSectionsType::class, [
                'sections' => $template->getSections(),
            ])->getForm();
        }

        $builder->setAttribute('section_collection_prototypes', $sectionsPrototypes);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['section_collection_prototypes'] = [];

        /** @var array<string, FormInterface> $sectionCollectionPrototypes */
        $sectionCollectionPrototypes = $form->getConfig()->getAttribute('section_collection_prototypes');
        foreach ($sectionCollectionPrototypes as $sectionName => $sectionCollectionPrototype) {
            $view->vars['section_collection_prototypes'][$sectionName] = $sectionCollectionPrototype->setParent($form)->createView($view);
        }
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_view';
    }

    private function addViewSectionsFromTemplateName(?string $templateName, FormInterface $form): void
    {
        $sections = [];
        if (null !== $templateName) {
            $template = $this->templateRegistry->get($templateName);
            $sections = $template->getSections();
        }
        $form->add('sections', ViewSectionsType::class, [
            'label' => false,
            'mapped' => false,
            'sections' => $sections,
        ]);
    }
}

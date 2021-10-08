<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type\CMSView;

use Setono\SyliusCMSPlugin\Form\EventSubscriber\SetQueryParameterValueOnObjectSubscriber;
use Setono\SyliusCMSPlugin\Form\Model\CMSView\CMSSectionModelFactoryInterface;
use Setono\SyliusCMSPlugin\Form\Model\CMSView\CMSViewModel;
use Setono\SyliusCMSPlugin\Form\Type\TemplateChoiceType;
use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Setono\SyliusCMSPlugin\Template\MetadataExtractorInterface;
use Setono\SyliusCMSPlugin\Template\Template;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/** @internal */
final class CMSViewModelType extends AbstractType
{
    private RequestStack $requestStack;

    private MetadataExtractorInterface $metadataExtractor;

    private TemplateRepositoryInterface $templateRepository;

    private CMSSectionModelFactoryInterface $sectionModelFactory;

    private string $viewModelClass;

    public function __construct(
        RequestStack $requestStack,
        MetadataExtractorInterface $metadataExtractor,
        TemplateRepositoryInterface $templateRepository,
        CMSSectionModelFactoryInterface $sectionModelFactory,
        string $viewModelClass
    ) {
        $this->requestStack = $requestStack;
        $this->metadataExtractor = $metadataExtractor;
        $this->templateRepository = $templateRepository;
        $this->sectionModelFactory = $sectionModelFactory;
        $this->viewModelClass = $viewModelClass;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('template', TemplateChoiceType::class)
            ->addEventSubscriber(new SetQueryParameterValueOnObjectSubscriber($this->requestStack))
            ->addEventSubscriber(new AddCodeFormSubscriber())
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var CMSViewModel|null $viewModel */
            $viewModel = $event->getData();
            if (null === $viewModel || '' === $viewModel->template) {
                return;
            }

            $this->addSectionsField($event->getForm(), null);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            /** @var array $data */
            $data = $event->getData();
            Assert::keyExists($data, 'template');
            /** @var string $templateName */
            $templateName = $data['template'];

            $template = $this->templateRepository->findOneBy(['code' => $templateName]);
            if (null === $template) {
                return;
            }
            $metadata = $this->metadataExtractor->extract(Template::createFromEntity($template));
            $sections = [];
            foreach ($metadata->getSections() as $sectionName) {
                $sections[$sectionName] = $this->sectionModelFactory->createFromNameAndBlocks($sectionName, []);
            }

            $this->addSectionsField($event->getForm(), $sections);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('configuration_type', null)
            ->setAllowedTypes('configuration_type', ['string', 'null'])
            ->setDefault('data_class', $this->viewModelClass)
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_model_view';
    }

    private function addSectionsField(FormInterface $form, ?iterable $data): void
    {
        $options = [
            'entry_type' => CMSSectionModelType::class,
        ];
        if (null !== $data) {
            $options['data'] = $data;
        }
        $form->add('sections', CollectionType::class, $options);
    }
}

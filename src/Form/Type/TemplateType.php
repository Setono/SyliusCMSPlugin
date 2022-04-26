<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Type;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Twig\Extractor\SectionExtractorInterface;
use Sylius\Bundle\ResourceBundle\Form\EventSubscriber\AddCodeFormSubscriber;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Webmozart\Assert\Assert;

final class TemplateType extends AbstractResourceType
{
    private SectionExtractorInterface $sectionExtractor;

    /**
     * @param list<string> $validationGroups
     */
    public function __construct(
        SectionExtractorInterface $sectionExtractor,
        string $dataClass,
        array $validationGroups = []
    ) {
        parent::__construct($dataClass, $validationGroups);

        $this->sectionExtractor = $sectionExtractor;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('source', TextareaType::class, [
                'label' => 'setono_sylius_cms.form.template.source',
            ])
            ->add('internalDescription', TextareaType::class, [
                'label' => 'setono_sylius_cms.form.internal_description',
                'required' => false,
            ])
            ->addEventSubscriber(new AddCodeFormSubscriber())
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
                /** @var mixed|TemplateInterface $template */
                $template = $event->getData();
                Assert::isInstanceOf($template, TemplateInterface::class);

                $source = $template->getSource();
                if (null === $source || '' === $source) {
                    $template->setSections([]);
                } else {
                    $template->setSections($this->sectionExtractor->extract($source));
                }
            });
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_cms_template';
    }
}

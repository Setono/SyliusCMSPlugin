<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\DataMapper;

use Setono\SyliusCMSPlugin\Model\ViewBlockInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Template\RegistryInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\DataAccessor\PropertyPathAccessor;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Webmozart\Assert\Assert;

final class ViewSectionsDataMapper extends DataMapper
{
    private RegistryInterface $templateRegistry;

    public function __construct(PropertyAccessorInterface $propertyAccessor, RegistryInterface $templateRegistry)
    {
        parent::__construct(new PropertyPathAccessor($propertyAccessor));

        $this->templateRegistry = $templateRegistry;
    }

    /**
     * @param mixed $data
     * @param \Traversable<mixed, FormInterface> $forms
     *
     * @psalm-suppress ParamNameMismatch,MoreSpecificImplementedParamType
     */
    public function mapFormsToData(iterable $forms, &$data): void
    {
        parent::mapFormsToData($forms, $data);

        /** @var FormInterface[] $arrayForms */
        $arrayForms = iterator_to_array($forms);

        Assert::isInstanceOf($data, ViewInterface::class);

        // TODO: merge instead of replacing
        foreach ($data->getViewBlocks() as $viewBlock) {
            $data->removeViewBlock($viewBlock);
        }

        /** @var FormInterface $viewBlockForm */
        foreach ($arrayForms['sections'] as $viewBlockForm) {
            /** @var array $formData */
            $formData = $viewBlockForm->getData();
            Assert::keyExists($formData, 'viewBlocks');

            /** @var ViewBlockInterface $viewBlock */
            foreach ($formData['viewBlocks'] as $viewBlock) {
                $viewBlock->setSection($viewBlockForm->getName());
                $data->addViewBlock($viewBlock);
            }
        }
    }

    /**
     * @param \Traversable<mixed, FormInterface> $forms
     * @param mixed $data
     *
     * @psalm-suppress ParamNameMismatch,MoreSpecificImplementedParamType
     */
    public function mapDataToForms($data, iterable $forms): void
    {
        // First, map parent so all fields are mapped the basic way
        parent::mapDataToForms($data, $forms);

        if (null === $data) {
            return;
        }

        if (!$data instanceof ViewInterface) {
            throw new UnexpectedTypeException($data, ViewInterface::class);
        }

        if (null === $data->getTemplate()) {
            return;
        }
        $templateName = $data->getTemplate();
        if (null === $templateName || !$this->templateRegistry->has($templateName)) {
            return;
        }

        $template = $this->templateRegistry->get($templateName);

        $sections = [];
        foreach ($template->getSections() as $sectionName) {
            $sections[$sectionName]['viewBlocks'] = $data->getViewBlocksInSection($sectionName);
        }

        /** @var array<array-key, FormInterface> $arrayForms */
        $arrayForms = iterator_to_array($forms);
        if (!\array_key_exists('sections', $arrayForms)) {
            return;
        }
        /** @var FormInterface $sectionsForm */
        $sectionsForm = $arrayForms['sections'];
        $sectionsForm->setData($sections);
    }
}

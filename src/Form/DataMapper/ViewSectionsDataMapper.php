<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\DataMapper;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\ViewBlock;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Template\MetadataExtractorInterface;
use Setono\SyliusCMSPlugin\Template\RegistryInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\DataAccessor\PropertyPathAccessor;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Traversable;
use Webmozart\Assert\Assert;

final class ViewSectionsDataMapper extends DataMapper
{
    private RegistryInterface $templateRegistry;

    private MetadataExtractorInterface $metadataExtractor;

    public function __construct(
        RegistryInterface $templateRegistry,
        MetadataExtractorInterface $metadataExtractor,
        PropertyAccessorInterface $propertyAccessor
    ) {
        parent::__construct(new PropertyPathAccessor($propertyAccessor));

        $this->templateRegistry = $templateRegistry;
        $this->metadataExtractor = $metadataExtractor;
    }

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
        $metadata = $this->metadataExtractor->extract($template);

        $sections = [];
        foreach ($metadata->getSections() as $sectionName) {
            $sections[$sectionName]['blocks'] = $data->getBlocksInSection($sectionName);
        }

        /**
         * @var FormInterface[] $arrayForms
         * @psalm-suppress PossiblyInvalidArgument
         */
        $arrayForms = iterator_to_array($forms);
        if (!\array_key_exists('sections', $arrayForms)) {
            return;
        }
        $arrayForms['sections']->setData($sections);
    }

    public function mapFormsToData(iterable $forms, &$data): void
    {
        parent::mapFormsToData($forms, $data);

        /**
         * @var FormInterface[] $arrayForms
         * @psalm-suppress PossiblyInvalidArgument
         */
        $arrayForms = iterator_to_array($forms);

        /* @psalm-var ViewInterface $data */
        Assert::isInstanceOf($data, ViewInterface::class);

        // TODO: merge instead of replacing
        foreach ($data->getViewBlocks() as $viewBlock) {
            $data->removeViewBlock($viewBlock);
        }

        /** @var FormInterface $viewBlockForm */
        foreach ($arrayForms['sections'] as $viewBlockForm) {
            /** @var array $formData */
            $formData = $viewBlockForm->getData();
            Assert::keyExists($formData, 'blocks');
            /**
             * @var int $priority
             * @var BlockInterface $block
             */
            foreach ($formData['blocks'] as $priority => $block) {
                $viewBlock = new ViewBlock();
                $viewBlock->setSection($viewBlockForm->getName());
                $viewBlock->setBlock($block);
                $viewBlock->setPriority($priority);
                $data->addViewBlock($viewBlock);
            }
        }
    }
}

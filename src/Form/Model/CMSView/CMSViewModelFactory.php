<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Form\Model\CMSView;

use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Setono\SyliusCMSPlugin\Template\MetadataExtractorInterface;
use Setono\SyliusCMSPlugin\Template\Template;

/** @internal */
final class CMSViewModelFactory implements CMSViewModelFactoryInterface
{
    /** @psalm-var class-string<CMSViewModel> */
    private string $viewModelClass;

    private MetadataExtractorInterface $metadataExtractor;

    private TemplateRepositoryInterface $templateRepository;

    private CMSSectionModelFactory $sectionModelFactory;

    /**
     * @psalm-param class-string<CMSViewModel> $viewModelClass
     */
    public function __construct(
        string $viewModelClass,
        MetadataExtractorInterface $metadataExtractor,
        TemplateRepositoryInterface $templateRepository,
        CMSSectionModelFactory $sectionModelFactory
    ) {
        $this->viewModelClass = $viewModelClass;
        $this->metadataExtractor = $metadataExtractor;
        $this->templateRepository = $templateRepository;
        $this->sectionModelFactory = $sectionModelFactory;
    }

    public function createFromView(ViewInterface $view): CMSViewModel
    {
        /** @psalm-suppress UnsafeInstantiation */
        $viewModel = new $this->viewModelClass();

        $viewModel->code = $view->getCode();
        $viewModel->template = $view->getTemplate() ?? '';
        $template = $this->templateRepository->findOneBy(['code' => $viewModel->template]);
        if (null !== $template) {
            $metadata = $this->metadataExtractor->extract(Template::createFromEntity($template));

            foreach ($metadata->getSections() as $sectionName) {
                $blocks = $view->getViewBlocksInSection($sectionName);
                $viewModel->sections[$sectionName] = $this->sectionModelFactory->createFromNameAndBlocks($sectionName, $blocks);
            }
        }

        return $viewModel;
    }
}

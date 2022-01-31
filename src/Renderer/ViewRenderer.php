<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Element\ViewElement;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Setono\SyliusCMSPlugin\Template\RegistryInterface;
use Twig\Environment;
use Webmozart\Assert\Assert;

/**
 * @implements RendererInterface<ViewInterface>
 */
final class ViewRenderer implements RendererInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    private ViewRepositoryInterface $viewRepository;

    private Environment $twig;

    private RegistryInterface $templateRegistry;

    /** @var RendererInterface<BlockInterface> */
    private RendererInterface $blockRenderer;

    private bool $debug;

    /**
     * @param RendererInterface<BlockInterface> $blockRenderer
     */
    public function __construct(
        ViewRepositoryInterface $viewRepository,
        Environment $twig,
        RegistryInterface $templateRegistry,
        RendererInterface $blockRenderer,
        bool $debug = false
    ) {
        $this->logger = new NullLogger();
        $this->viewRepository = $viewRepository;
        $this->twig = $twig;
        $this->templateRegistry = $templateRegistry;
        $this->blockRenderer = $blockRenderer;
        $this->debug = $debug;
    }

    public function render($element): Response
    {
        if (is_string($element)) {
            $code = $element;
            $element = $this->viewRepository->findOneByCode($code);
            if (null === $element) {
                $this->logger->error(sprintf('The view "%s" is not defined', $code));

                return $this->renderNonExistingView($code);
            }
        }
        Assert::isInstanceOf($element, ViewInterface::class);

        $templateCode = $element->getTemplate();
        Assert::notNull($templateCode);

        if (!$this->templateRegistry->has($templateCode)) {
            $this->logger->error(sprintf('The template "%s" is not defined', $templateCode));

            return $this->renderNonExistingTemplate($templateCode);
        }

        $template = $this->templateRegistry->get($templateCode);

        $elementIds = [];

        $context = [];
        foreach ($element->getViewBlocks() as $viewBlock) {
            $block = $viewBlock->getBlock();
            if (null === $block) {
                continue;
            }

            $key = sprintf('sscms_%s', (string) $viewBlock->getSection());
            $response = $this->blockRenderer->render($block);
            $context[$key] = isset($context[$key]) ? $context[$key] . $response->getContent() : $response->getContent();

            $elementIds = array_merge($response->getElementIds(), $elementIds);
        }

        $response = new Response($this->twig->render('@SetonoSyliusCMSPlugin/view.html.twig', [
            'view' => new ViewElement($element, $this->twig->render($template->getCode(), $context)),
        ]), $elementIds);
        $response->addElementId(ElementId::fromResource($element));

        return $response;
    }

    private function renderNonExistingView(string $code): Response
    {
        if (!$this->debug) {
            return Response::empty();
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/view/non_existing.html.twig', [
            'code' => $code,
        ]));
    }

    private function renderNonExistingTemplate(string $templateCode): Response
    {
        if (!$this->debug) {
            return Response::empty();
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/view/non_existing_template.html.twig', [
            'code' => $templateCode,
        ]));
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}

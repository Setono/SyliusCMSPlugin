<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Element\CarouselElement;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Setono\SyliusCMSPlugin\Repository\CarouselRepositoryInterface;
use Twig\Environment;
use Webmozart\Assert\Assert;

/**
 * @implements RendererInterface<CarouselInterface>
 */
final class CarouselRenderer implements RendererInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    private CarouselRepositoryInterface $carouselRepository;

    private Environment $twig;

    /** @var RendererInterface<BlockInterface> */
    private RendererInterface $blockRenderer;

    private string $template;

    private bool $debug;

    /**
     * @param RendererInterface<BlockInterface> $blockRenderer
     */
    public function __construct(
        CarouselRepositoryInterface $carouselRepository,
        Environment $twig,
        RendererInterface $blockRenderer,
        string $template,
        bool $debug
    ) {
        $this->logger = new NullLogger();
        $this->carouselRepository = $carouselRepository;
        $this->twig = $twig;
        $this->blockRenderer = $blockRenderer;
        $this->template = $template;
        $this->debug = $debug;
    }

    public function render($element): Response
    {
        if (is_string($element)) {
            $code = $element;
            $element = $this->carouselRepository->findOneByCode($code);
            if (null === $element) {
                $this->logger->error(sprintf('The carousel "%s" is not defined', $code));

                return $this->renderNonExistingCarousel($code);
            }
        }
        Assert::isInstanceOf($element, CarouselInterface::class);

        $elementIds = [];

        $key = 'sscms_carousel_elements';
        $context = [];
        foreach ($element->getCarouselBlocks() as $carouselBlock) {
            $block = $carouselBlock->getBlock();
            if (null === $block) {
                continue;
            }

            $response = $this->blockRenderer->render($block);
            $context[$key][] = $response->getContent();

            $elementIds = array_merge($elementIds, $response->getElementIds());
        }
        $context['carousel'] = $element;

        $response = new Response($this->twig->render('@SetonoSyliusCMSPlugin/carousel.html.twig', [
            'carousel' => new CarouselElement($element, $this->twig->render($this->template, $context)),
        ]), $elementIds);
        $response->addElementId(ElementId::fromResource($element));

        return $response;
    }

    private function renderNonExistingCarousel(string $code): Response
    {
        if (!$this->debug) {
            return Response::empty();
        }

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/carousel/non_existing.html.twig', [
            'code' => $code,
        ]));
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}

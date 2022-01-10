<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Element\CarouselElement;
use Setono\SyliusCMSPlugin\Repository\CarouselRepositoryInterface;
use Setono\SyliusCMSPlugin\Stack\ElementStackInterface;
use Twig\Environment;

final class CarouselRenderer implements CarouselRendererInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    private CarouselRepositoryInterface $carouselRepository;

    private Environment $twig;

    private BlockRendererInterface $blockRenderer;

    private ElementStackInterface $elementStack;

    private string $template;

    private bool $debug;

    public function __construct(
        CarouselRepositoryInterface $carouselRepository,
        Environment $twig,
        BlockRendererInterface $blockRenderer,
        ElementStackInterface $elementStack,
        string $template,
        bool $debug
    ) {
        $this->logger = new NullLogger();
        $this->carouselRepository = $carouselRepository;
        $this->twig = $twig;
        $this->blockRenderer = $blockRenderer;
        $this->elementStack = $elementStack;
        $this->template = $template;
        $this->debug = $debug;
    }

    public function render($carousel): string
    {
        if (is_string($carousel)) {
            $code = $carousel;
            $carousel = $this->carouselRepository->findOneByCode($code);
            if (null === $carousel) {
                $this->logger->error(sprintf('The carousel "%s" is not defined', $code));

                return $this->renderNonExistingCarousel($code);
            }
        }

        $key = 'sscms_carousel_elements';
        $context = [];
        foreach ($carousel->getCarouselBlocks() as $carouselBlock) {
            $block = $carouselBlock->getBlock();
            if (null === $block) {
                continue;
            }

            $content = $this->blockRenderer->render($block);
            $context[$key][] = $content;
        }
        $context['carousel'] = $carousel;

        $this->elementStack->push($carousel);

        return $this->twig->render('@SetonoSyliusCMSPlugin/carousel.html.twig', [
            'carousel' => new CarouselElement($carousel, $this->twig->render($this->template, $context)),
        ]);
    }

    private function renderNonExistingCarousel(string $code): string
    {
        if (!$this->debug) {
            return '';
        }

        return $this->twig->render('@SetonoSyliusCMSPlugin/carousel/debug_message.twig', [
            'code' => $code,
        ]);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}

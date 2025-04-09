<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Exception\NonExistingElementException;
use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\Element;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Repository\ElementRepositoryInterface;
use Setono\SyliusCMSPlugin\Twig\LogicalTemplateName;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

final class GenericElementLoader implements LoaderInterface, LoggerAwareInterface
{
    private LoggerInterface $logger;

    /** @var array<string, ElementInterface> */
    private array $cache = [];

    public function __construct(
        private readonly ElementRepositoryInterface $elementRepository,
        private readonly TwigGeneratorInterface $twigGenerator,
        private readonly string $supportsType,
    ) {
        $this->logger = new NullLogger();
    }

    /**
     * This method is always called before any of the other methods in the LoaderInterface (@see \Twig\Loader\ChainLoader)
     * This implies that we can expect the $name to be valid in all other methods in this class
     *
     * NOTICE
     * The interface states that this method should throw a LoaderError if the $name does not exist.
     * We only do this in subsequent methods (i.e. getSourceContext and isFresh). This is because Twig
     * will call this method everytime Twig needs a cache key and to load a template (also existing compiled ones)
     * Twig needs the cache key, hence exists() is called EVERY time a template is loaded and with our approach this
     * would mean we needed to hit the database for EVERY CMS element referenced throughout the application
     *
     * @param string $name
     */
    public function exists($name): bool
    {
        try {
            $logicalTemplateName = LogicalTemplateName::createFromString($name);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $logicalTemplateName->type === $this->supportsType;
    }

    /**
     * @param string $name
     */
    public function getCacheKey($name): string
    {
        return (string) LogicalTemplateName::createFromString($name);
    }

    /**
     * @param string $name
     */
    public function getSourceContext($name): Source
    {
        $logicalTemplateName = LogicalTemplateName::createFromString($name);

        try {
            $element = $this->getElement($logicalTemplateName);
        } catch (NonExistingElementException) {
            $this->logger->error(sprintf(
                'Element with type "%s" and code "%s" does not exist',
                $logicalTemplateName->type,
                $logicalTemplateName->code,
            ));

            return new Source('', (string) $logicalTemplateName);
        }

        return new Source($this->twigGenerator->generate($element, $logicalTemplateName->channelCode, $logicalTemplateName->localeCode, [
            'channelCode' => $logicalTemplateName->channelCode,
            'localeCode' => $logicalTemplateName->localeCode,
        ]), (string) $logicalTemplateName);
    }

    /**
     * todo shouldn't this method just return true always?
     *
     * @param string $name
     * @param int $time
     */
    public function isFresh($name, $time): bool
    {
        $logicalTemplateName = LogicalTemplateName::createFromString($name);

        $element = $this->getElement($logicalTemplateName);

        $updatedAt = $element->getUpdatedAt();
        if (null === $updatedAt) {
            return false;
        }

        return $updatedAt->getTimestamp() <= $time;
    }

    /**
     * @throws NonExistingElementException when the logical template name does not exist
     * @throws LoaderError when the database connection isn't there or the respective tables hasn't been created yet
     */
    private function getElement(LogicalTemplateName $logicalTemplateName): ElementInterface
    {
        if (!array_key_exists($logicalTemplateName->value, $this->cache)) {
            try {
                $element = $this->elementRepository->findOneByCode(
                    $logicalTemplateName->code,
                    $logicalTemplateName->channelCode,
                    $logicalTemplateName->localeCode,
                );
            } catch (Exception $e) {
                // exceptions can be thrown here when:
                // 1. there's no connection to the database
                // 2. the table has not been created yet
                // 3. some new fields has been created in new versions of the plugin, but not migrated yet
                // 4. other things happen that corresponds to the child classes of \Doctrine\DBAL\Exception
                throw new LoaderError($e->getMessage(), -1, null, $e);
            }

            if (null === $element) {
                throw NonExistingElementException::fromLogicalTemplateName($logicalTemplateName);
            }

            $this->cache[$logicalTemplateName->value] = $element;
        }

        return $this->cache[$logicalTemplateName->value];
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}

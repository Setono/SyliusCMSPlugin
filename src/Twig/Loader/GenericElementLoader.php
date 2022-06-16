<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader;

use Exception;
use InvalidArgumentException;
use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\Element;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Repository\ElementRepositoryInterface;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;
use Webmozart\Assert\Assert;

final class GenericElementLoader implements LoaderInterface
{
    /** @var array<string, ElementInterface> */
    private array $cache = [];

    private ElementRepositoryInterface $elementRepository;

    private TwigGeneratorInterface $twigGenerator;

    private string $supportsType;

    public function __construct(
        ElementRepositoryInterface $blockRepository,
        TwigGeneratorInterface $twigGenerator,
        string $supportsType
    ) {
        Assert::oneOf($supportsType, Element::getTypes());

        $this->elementRepository = $blockRepository;
        $this->twigGenerator = $twigGenerator;
        $this->supportsType = $supportsType;
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
     */
    public function exists($name): bool
    {
        try {
            $logicalTemplateName = LogicalTemplateName::createFromString($name);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return $logicalTemplateName->type === $this->supportsType;
    }

    public function getCacheKey($name): string
    {
        return (string) LogicalTemplateName::createFromString($name);
    }

    public function getSourceContext($name): Source
    {
        $logicalTemplateName = LogicalTemplateName::createFromString($name);

        // todo should this take the channel and locale into consideration?
        $element = $this->getElement($logicalTemplateName);

        /** @psalm-suppress PossiblyNullArgument */
        return new Source($this->twigGenerator->generate($element, [
            'channelCode' => $logicalTemplateName->channelCode,
            'localeCode' => $logicalTemplateName->localeCode,
        ]), (string) $logicalTemplateName);
    }

    public function isFresh($name, $time): bool
    {
        $logicalTemplateName = LogicalTemplateName::createFromString($name);

        // todo should this take the channel and locale into consideration?
        $element = $this->getElement($logicalTemplateName);

        /** @psalm-suppress PossiblyNullReference */
        $updatedAt = $element->getUpdatedAt();
        if (null === $updatedAt) {
            return false;
        }

        return $updatedAt->getTimestamp() <= $time;
    }

    /**
     * @throws LoaderError when the logical template name does not exist, the database connection isn't there or
     * the respective tables hasn't been created yet
     */
    private function getElement(LogicalTemplateName $logicalTemplateName): ElementInterface
    {
        if (!array_key_exists($logicalTemplateName->code, $this->cache)) {
            try {
                $element = $this->elementRepository->findOneByCode($logicalTemplateName->code);
            } catch (Exception $e) {
                // exceptions can be thrown here when:
                // 1. there's no connection to the database
                // 2. the table has not been created yet
                // 3. some new fields has been created in new versions of the plugin, but not migrated yet
                // 4. other things happen that corresponds to the child classes of \Doctrine\DBAL\Exception
                throw new LoaderError($e->getMessage(), -1, null, $e);
            }

            if (null === $element) {
                throw new LoaderError(sprintf(
                    'The logical template name "%s" does not exist',
                    (string) $logicalTemplateName
                ));
            }

            $this->cache[$logicalTemplateName->code] = $element;
        }

        return $this->cache[$logicalTemplateName->code];
    }
}

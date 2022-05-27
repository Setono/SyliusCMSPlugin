<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig\Loader\ElementLoader;

use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Setono\SyliusCMSPlugin\Generator\Twig\TwigGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\Element;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Repository\ElementRepositoryInterface;
use Webmozart\Assert\Assert;

final class GenericElementLoader implements ElementLoaderInterface
{
    /** @var array<string, ElementInterface|null> */
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

    public function exists(LogicalTemplateName $logicalTemplateName): bool
    {
        // todo should this take the channel and locale into consideration?
        return $this->findElement($logicalTemplateName) !== null;
    }

    public function getSource(LogicalTemplateName $logicalTemplateName): string
    {
        // todo should this take the channel and locale into consideration?
        $element = $this->findElement($logicalTemplateName, true);

        /** @psalm-suppress PossiblyNullArgument */
        return $this->twigGenerator->generate($element, [
            'localeCode' => $logicalTemplateName->localeCode,
        ]);
    }

    public function isFresh(LogicalTemplateName $logicalTemplateName, int $time): bool
    {
        // todo should this take the channel and locale into consideration?
        $element = $this->findElement($logicalTemplateName, true);

        /** @psalm-suppress PossiblyNullReference */
        $updatedAt = $element->getUpdatedAt();
        if (null === $updatedAt) {
            return false;
        }

        return $updatedAt->getTimestamp() <= $time;
    }

    public function supports(LogicalTemplateName $logicalTemplateName): bool
    {
        return $this->supportsType === $logicalTemplateName->type;
    }

    private function findElement(
        LogicalTemplateName $logicalTemplateName,
        bool $throwOnNull = false
    ): ?ElementInterface {
        if (!array_key_exists($logicalTemplateName->code, $this->cache)) {
            try {
                $element = $this->elementRepository->findOneByCode($logicalTemplateName->code);
            } catch (ConnectionException | TableNotFoundException $e) {
                // these exceptions are thrown either when there's no connection to the database
                // or when the respective element tables hasn't been created yet
                return null;
            }

            $this->cache[$logicalTemplateName->code] = $element;
        }

        if ($throwOnNull && null === $this->cache[$logicalTemplateName->code]) {
            throw new \InvalidArgumentException(sprintf(
                'The logical template name "%s" does not exist',
                (string) $logicalTemplateName
            ));
        }

        return $this->cache[$logicalTemplateName->code];
    }
}

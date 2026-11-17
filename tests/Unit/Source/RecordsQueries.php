<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Setono\SyliusCMSPlugin\Query\VisibleTaxonsQueryInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\Taxon;

/**
 * Fake visible queries on an entity manager that records the queries a source asks it for, without a database, so a
 * unit test reads the DQL and the parameters a source pages through
 */
trait RecordsQueries
{
    /** @var list<Query> */
    private array $queries = [];

    /**
     * @template T of object
     *
     * @param class-string<T> $classOrInterface
     *
     * @return ObjectProphecy<T>
     */
    abstract protected function prophesize(?string $classOrInterface = null): ObjectProphecy;

    private function visibleProducts(): VisibleProductsQueryInterface
    {
        $entityManager = $this->recordingEntityManager();
        $query = $this->prophesize(VisibleProductsQueryInterface::class);
        $query->createQueryBuilder(Argument::any())->will(static fn (): QueryBuilder => (new QueryBuilder($entityManager))
            ->select('product')
            ->from(Product::class, 'product')
            ->andWhere('product.enabled = :cms_visible_enabled')
            ->setParameter('cms_visible_enabled', true));

        return $query->reveal();
    }

    private function visibleTaxons(): VisibleTaxonsQueryInterface
    {
        $entityManager = $this->recordingEntityManager();
        $query = $this->prophesize(VisibleTaxonsQueryInterface::class);
        $query->createQueryBuilder(Argument::any())->will(static fn (): QueryBuilder => (new QueryBuilder($entityManager))
            ->select('taxon')
            ->from(Taxon::class, 'taxon')
            ->andWhere('taxon.enabled = :cms_visible_enabled')
            ->setParameter('cms_visible_enabled', true));

        return $query->reveal();
    }

    /**
     * The DQL of the one query asked for
     */
    private function dql(): string
    {
        self::assertCount(1, $this->queries, 'The source asked for one query');

        return (string) $this->queries[0]->getDQL();
    }

    /**
     * The value of a parameter of the one query asked for
     */
    private function parameter(string $name): mixed
    {
        self::assertCount(1, $this->queries, 'The source asked for one query');
        $parameter = $this->queries[0]->getParameter($name);
        self::assertNotNull($parameter, sprintf('The query has the parameter "%s"', $name));

        return $parameter->getValue();
    }

    private function recordingEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getConfiguration()->willReturn(new Configuration());
        $entityManager->getExpressionBuilder()->willReturn(new Expr());

        $queries = &$this->queries;
        $entityManager->createQuery(Argument::type('string'))->will(static function (array $arguments) use ($entityManager, &$queries): Query {
            $query = new Query($entityManager->reveal());
            $query->setDQL((string) $arguments[0]);
            $queries[] = $query;

            return $query;
        });

        return $entityManager->reveal();
    }
}

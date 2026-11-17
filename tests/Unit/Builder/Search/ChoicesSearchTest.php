<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\Search;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Builder\Search\ChoicesSearch;
use Setono\SyliusCMSPlugin\Builder\Search\SearchResult;
use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;

final class ChoicesSearchTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_finds_the_choices_whose_name_or_value_holds_the_query_ignoring_case_in_the_providers_order(): void
    {
        $search = new ChoicesSearch($this->provider(['@guest' => 'Gæster', '@no_group' => 'Uden gruppe', 'wholesale' => 'Engros', 'GÆST_VIP' => 'VIP']));

        self::assertEquals([new SearchResult('@guest', 'Gæster'), new SearchResult('GÆST_VIP', 'VIP')], $search->search(' gÆst ', 'da_DK'));
        self::assertEquals([new SearchResult('wholesale', 'Engros')], $search->search('WHOLE', 'da_DK'));
        self::assertSame([], $search->search('retail', 'da_DK'));
        self::assertEquals([new SearchResult('apples', 'ÆBLER')], (new ChoicesSearch($this->provider(['apples' => 'ÆBLER'])))->search('æbler', 'da_DK'));
    }

    /**
     * @test
     */
    public function it_finds_every_choice_for_an_empty_query_but_at_most_a_page_of_them(): void
    {
        $choices = [];
        for ($i = 1; $i <= 25; ++$i) {
            $choices['group_' . $i] = 'Group ' . $i;
        }

        $results = (new ChoicesSearch($this->provider($choices)))->search('', 'en_US');

        self::assertCount(20, $results);
        self::assertEquals(new SearchResult('group_20', 'Group 20'), $results[19]);
    }

    /**
     * @test
     */
    public function it_writes_a_numeric_value_as_a_string_and_asks_in_the_locale(): void
    {
        $provider = $this->prophesize(ChoicesProviderInterface::class);
        $provider->getChoices('da_DK')->willReturn(['42' => 'Butik 42']);

        self::assertSame([['ref' => '42', 'name' => 'Butik 42', 'thumbnail' => null]], json_decode((string) json_encode((new ChoicesSearch($provider->reveal()))->search('42', 'da_DK')), true));
    }

    /**
     * @test
     */
    public function it_looks_choices_up_by_value_in_the_providers_order(): void
    {
        $search = new ChoicesSearch($this->provider(['@guest' => 'Guests', 'wholesale' => 'Wholesale', 'vip' => 'VIP']));

        self::assertEquals([new SearchResult('@guest', 'Guests'), new SearchResult('vip', 'VIP')], $search->find(['vip', 'deleted', '@guest'], 'en_US'));
        self::assertSame([], $search->find([], 'en_US'));
    }

    /**
     * @param array<string, string> $choices
     */
    private function provider(array $choices): ChoicesProviderInterface
    {
        $provider = $this->prophesize(ChoicesProviderInterface::class);
        $provider->getChoices(Argument::type('string'))->willReturn($choices);

        return $provider->reveal();
    }
}

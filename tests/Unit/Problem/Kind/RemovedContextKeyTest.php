<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\RemovedContextKey;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A snippet that needs a context key the code no longer declares is kept, isn't shown in any slot, and is flagged.
 * See "Mismatches" in docs/spec/slots.md and "Problems" in docs/spec/admin.md
 */
final class RemovedContextKeyTest extends TestCase
{
    /**
     * @test
     */
    public function it_finds_each_context_key_a_snippet_declares_that_the_code_no_longer_declares_as_a_warning_fixed_in_the_builder(): void
    {
        $snippet = new Snippet();
        $snippet->setContext(['product', 'brand', 'store_location']);

        self::assertEquals([
            new Problem('removed_context_key', Severity::Warning, $snippet, new TranslatableMessage('setono_sylius_cms.problem.removed_context_key', ['%key%' => 'brand']), Fix::inBuilder($snippet)),
            new Problem('removed_context_key', Severity::Warning, $snippet, new TranslatableMessage('setono_sylius_cms.problem.removed_context_key', ['%key%' => 'store_location']), Fix::inBuilder($snippet)),
        ], [...self::kind()->findIn($snippet)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_a_snippet_that_declares_only_declared_keys_or_an_entry_that_is_not_a_snippet(): void
    {
        $snippet = new Snippet();
        $snippet->setContext(['product']);

        self::assertSame([], [...self::kind()->findIn($snippet)]);
        self::assertSame([], [...self::kind()->findIn(new Snippet())]);
        self::assertSame([], [...self::kind()->findIn(new Page())]);
    }

    private static function kind(): RemovedContextKey
    {
        return new RemovedContextKey(new ContextKeyRegistry(['product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product']]));
    }
}

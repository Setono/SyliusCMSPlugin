<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Builder\RegistryExporter;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistryExporterTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_exports_the_block_types_with_their_field_schemas_in_the_current_locale(): void
    {
        $registry = $this->exporter()->export();

        self::assertSame([
            [
                'code' => 'heading',
                'label' => 'Overskrift',
                'version' => 1,
                'fields' => [
                    [
                        'name' => 'text',
                        'schema' => [
                            'type' => 'string',
                            'x-cms-options' => ['maxLength' => null],
                            'x-cms-type' => 'text',
                            'x-cms-label' => '[setono_sylius_cms.field.heading.text]',
                            'x-cms-help' => null,
                            'x-cms-required' => true,
                            'x-cms-translatable' => true,
                        ],
                    ],
                    [
                        'name' => 'level',
                        'schema' => [
                            'type' => 'string',
                            'enum' => ['h1', 'h2', 'h3', 'h4'],
                            'x-cms-options' => [
                                'choices' => [
                                    'h1' => '[setono_sylius_cms.choice.heading_level.h1]',
                                    'h2' => '[setono_sylius_cms.choice.heading_level.h2]',
                                    'h3' => '[setono_sylius_cms.choice.heading_level.h3]',
                                    'h4' => '[setono_sylius_cms.choice.heading_level.h4]',
                                ],
                                'multiple' => false,
                            ],
                            'default' => 'h2',
                            'x-cms-type' => 'choice',
                            'x-cms-label' => '[setono_sylius_cms.field.heading.level]',
                            'x-cms-help' => null,
                            'x-cms-required' => false,
                            'x-cms-translatable' => false,
                        ],
                    ],
                ],
            ],
        ], $registry['blockTypes']);
    }

    /**
     * @test
     */
    public function it_exports_the_builder_ui_strings_of_the_locale_and_its_fallbacks(): void
    {
        $registry = $this->exporter()->export();

        self::assertSame([
            'setono_sylius_cms.builder.saved' => 'Gemt',
            'setono_sylius_cms.builder.saving' => '[setono_sylius_cms.builder.saving]',
        ], $registry['strings']);
    }

    private function exporter(): RegistryExporter
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::type('string'))->will(static fn (array $arguments): string => sprintf('[%s]', is_string($arguments[0] ?? null) ? $arguments[0] : ''));
        $translator->trans('setono_sylius_cms.block_type.heading')->willReturn('Overskrift');
        $translator->trans('setono_sylius_cms.builder.saved')->willReturn('Gemt')->shouldBeCalledOnce();

        $fallback = new MessageCatalogue('en', ['messages' => [
            'setono_sylius_cms.builder.saving' => 'Saving…',
            'setono_sylius_cms.builder.saved' => 'Saved',
            'sylius.ui.save' => 'Save',
            42 => 'A message with a numeric id',
        ]]);
        $catalogue = new MessageCatalogue('da', ['messages' => ['setono_sylius_cms.builder.saving' => 'Gemmer…', 'setono_sylius_cms.builder.saved' => 'Gemt']]);
        $catalogue->addFallbackCatalogue($fallback);

        $catalogues = $this->prophesize(TranslatorBagInterface::class);
        $catalogues->getCatalogue()->willReturn($catalogue);

        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator->reveal()),
        ]));

        return new RegistryExporter(
            new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()),
            $handlers,
            $translator->reveal(),
            $catalogues->reveal(),
        );
    }
}

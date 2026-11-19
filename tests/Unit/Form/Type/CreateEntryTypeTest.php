<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Form\Type;

use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\StartingPointChoice;
use Setono\SyliusCMSPlugin\Form\Type\CreateEntryType;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Product\Generator\SlugGenerator;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Validation;

/**
 * The create dialog's form. See "Create dialog" in docs/spec/admin.md
 */
final class CreateEntryTypeTest extends TypeTestCase
{
    use ProphecyTrait;

    private const LAYOUTS = [
        'standard' => ['label' => 'setono_sylius_cms.layout.page.standard', 'thumbnail' => '/bundles/setonosyliuscmsplugin/thumbnail/layout/page/standard.svg'],
        'landing' => ['label' => 'app.cms.layout.landing', 'thumbnail' => null],
    ];

    private const EDITOR_PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    /** @var list<ChannelInterface> the store's channels, by name */
    private array $channels = [];

    /**
     * @test
     */
    public function it_submits_a_title_and_a_slug(): void
    {
        $form = $this->factory->create(CreateEntryType::class);

        $form->submit(['title' => 'About us', 'slug' => 'about/team']);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame(['title' => 'About us', 'slug' => 'about/team'], $form->getData());
    }

    /**
     * @test
     *
     * @dataProvider provideTitles
     */
    public function it_fills_the_slug_from_the_title_with_syliuss_slug_generator_when_the_editor_leaves_it_empty(string $title, string $slug): void
    {
        $form = $this->factory->create(CreateEntryType::class);

        $form->submit(['title' => $title, 'slug' => '']);

        self::assertTrue($form->isValid());
        self::assertSame(['title' => trim($title), 'slug' => $slug], $form->getData());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTitles(): iterable
    {
        yield 'words' => ['About us', 'about-us'];
        yield 'letters with accents' => ['Über uns', 'uber-uns'];
        yield 'an apostrophe' => ["Shopper's guide", 'shopper-s-guide'];
        yield 'punctuation and spaces around it' => ['  Delivery & returns!  ', 'delivery-returns'];
    }

    /**
     * @test
     */
    public function it_fills_a_slug_that_was_not_sent_from_the_title_too(): void
    {
        $form = $this->factory->create(CreateEntryType::class);

        $form->submit(['title' => 'About us']);

        self::assertSame(['title' => 'About us', 'slug' => 'about-us'], $form->getData());
    }

    /**
     * @test
     */
    public function it_counts_a_slug_of_spaces_as_empty(): void
    {
        $form = $this->factory->create(CreateEntryType::class);

        $form->submit(['title' => 'About us', 'slug' => '  ']);

        self::assertSame(['title' => 'About us', 'slug' => 'about-us'], $form->getData());
    }

    /**
     * @test
     */
    public function it_keeps_a_slug_the_editor_typed(): void
    {
        $form = $this->factory->create(CreateEntryType::class);

        $form->submit(['title' => 'About us', 'slug' => 'company/about']);

        self::assertSame(['title' => 'About us', 'slug' => 'company/about'], $form->getData());
    }

    /**
     * @test
     */
    public function it_has_no_slug_to_fill_without_a_title(): void
    {
        $form = $this->factory->create(CreateEntryType::class);

        $form->submit(['title' => '', 'slug' => '']);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('title')->getErrors());
        self::assertCount(1, $form->get('slug')->getErrors());
    }

    /**
     * @test
     */
    public function it_has_no_slug_for_a_content_type_without_its_own_url(): void
    {
        $form = $this->factory->create(CreateEntryType::class, null, ['slug' => false]);

        $form->submit(['title' => 'Footer']);

        self::assertFalse($form->has('slug'));
        self::assertTrue($form->isValid());
        self::assertSame(['title' => 'Footer'], $form->getData());
    }

    /**
     * @test
     */
    public function it_offers_the_stores_channels_by_name_all_picked_to_start_with(): void
    {
        $this->channels = [self::channel('B2B', 'Wholesale'), self::channel('MOBILE', null), self::channel(null, 'Unfinished'), self::channel('WEB', 'Web store')];
        $form = $this->factory->create(CreateEntryType::class);
        $view = $form->createView();

        /** @var array{label: string, expanded: bool, multiple: bool, choices: list<ChoiceView>, choice_translation_domain: bool|string, data: list<string>} $vars */
        $vars = $view['channels']->vars;
        self::assertSame('setono_sylius_cms.form.entry.channels', $vars['label']);
        self::assertTrue($vars['expanded']);
        self::assertTrue($vars['multiple']);
        self::assertFalse($vars['choice_translation_domain']);
        // A channel without a name is offered by its code, and one without a code isn't offered
        self::assertSame(['B2B' => 'Wholesale', 'MOBILE' => 'MOBILE', 'WEB' => 'Web store'], self::labels($vars['choices']));
        self::assertSame(['B2B', 'MOBILE', 'WEB'], $vars['data']);

        $form->submit(['title' => 'About us', 'slug' => 'about-us', 'channels' => ['WEB']]);

        self::assertTrue($form->isValid());
        self::assertSame(['title' => 'About us', 'slug' => 'about-us', 'channels' => [2 => 'WEB']], $form->getData());
    }

    /**
     * @test
     */
    public function it_refuses_a_channel_it_does_not_offer(): void
    {
        $this->channels = [self::channel('B2B', 'Wholesale'), self::channel('WEB', 'Web store')];
        $form = $this->factory->create(CreateEntryType::class);

        $form->submit(['title' => 'About us', 'slug' => 'about-us', 'channels' => ['POS']]);

        self::assertFalse($form->isValid());
    }

    /**
     * @test
     */
    public function it_has_no_channels_to_pick_in_a_store_with_one(): void
    {
        $this->channels = [self::channel('WEB', 'Web store')];
        $form = $this->factory->create(CreateEntryType::class);

        self::assertFalse($form->has('channels'));
    }

    /**
     * @test
     */
    public function it_offers_the_layouts_as_wireframe_cards_with_the_first_picked_to_start_with(): void
    {
        $form = $this->factory->create(CreateEntryType::class, null, ['layouts' => self::LAYOUTS]);
        $view = $form->createView();

        /** @var array{label: string, expanded: bool, multiple: bool, choices: list<ChoiceView>, data: string} $vars */
        $vars = $view['layout']->vars;
        self::assertSame('setono_sylius_cms.form.entry.layout', $vars['label']);
        self::assertTrue($vars['expanded']);
        self::assertFalse($vars['multiple']);
        self::assertSame(['standard' => 'setono_sylius_cms.layout.page.standard', 'landing' => 'app.cms.layout.landing'], self::labels($vars['choices']));
        self::assertSame(['data-thumbnail' => '/bundles/setonosyliuscmsplugin/thumbnail/layout/page/standard.svg'], $vars['choices'][0]->attr);
        self::assertSame([], $vars['choices'][1]->attr, 'A layout without a thumbnail has none');
        self::assertSame('standard', $vars['data']);

        $form->submit(['title' => 'About us', 'slug' => 'about-us', 'layout' => 'landing']);

        self::assertTrue($form->isValid());
        self::assertSame(['title' => 'About us', 'slug' => 'about-us', 'layout' => 'landing'], $form->getData());
    }

    /**
     * @test
     */
    public function it_refuses_a_layout_it_does_not_offer(): void
    {
        $form = $this->factory->create(CreateEntryType::class, null, ['layouts' => self::LAYOUTS]);

        $form->submit(['title' => 'About us', 'slug' => 'about-us', 'layout' => 'gone']);

        self::assertFalse($form->isValid());
    }

    /**
     * @test
     */
    public function it_has_no_layout_to_pick_for_a_content_type_with_one_or_none(): void
    {
        self::assertFalse($this->factory->create(CreateEntryType::class, null, ['layouts' => ['standard' => self::LAYOUTS['standard']]])->has('layout'));
        self::assertFalse($this->factory->create(CreateEntryType::class)->has('layout'));
    }

    /**
     * @test
     */
    public function it_offers_blank_and_the_starting_points_as_cards_with_blank_picked_to_start_with(): void
    {
        $form = $this->factory->create(CreateEntryType::class, null, ['starting_points' => [self::startingPoint('landing', thumbnail: '/build/landing.svg'), self::startingPoint(self::EDITOR_PRESET)]]);
        $view = $form->createView();

        /** @var array{label: string, expanded: bool, multiple: bool, required: bool, placeholder: string, choices: list<ChoiceView>, data: string|null} $vars */
        $vars = $view['startingPoint']->vars;
        self::assertSame('setono_sylius_cms.form.entry.starting_point', $vars['label']);
        self::assertTrue($vars['expanded']);
        self::assertFalse($vars['multiple']);
        // Blank is the field's placeholder, which leaves the entry's tree as the content type starts it
        self::assertFalse($vars['required']);
        self::assertSame('setono_sylius_cms.form.entry.blank', $vars['placeholder']);
        self::assertSame(['landing' => 'The landing', self::EDITOR_PRESET => 'The ' . self::EDITOR_PRESET], self::labels($vars['choices']));
        self::assertSame(['data-thumbnail' => '/build/landing.svg', 'data-description' => 'About landing'], $vars['choices'][0]->attr);
        self::assertSame(['data-description' => 'About ' . self::EDITOR_PRESET], $vars['choices'][1]->attr);
        self::assertNull($vars['data']);
        self::assertTrue($view['startingPoint']['placeholder']->vars['checked']);

        $form->submit(['title' => 'About us', 'slug' => 'about-us', 'startingPoint' => self::EDITOR_PRESET]);

        self::assertTrue($form->isValid());
        self::assertSame(['title' => 'About us', 'slug' => 'about-us', 'startingPoint' => self::EDITOR_PRESET], $form->getData());
    }

    /**
     * @test
     */
    public function it_picks_the_default_starting_point_to_start_with_and_takes_blank(): void
    {
        $form = $this->factory->create(CreateEntryType::class, null, ['starting_points' => [self::startingPoint('content'), self::startingPoint('landing', default: true)]]);

        /** @var array{data: string|null} $vars */
        $vars = $form->createView()['startingPoint']->vars;
        self::assertSame('landing', $vars['data']);

        $form->submit(['title' => 'About us', 'slug' => 'about-us', 'startingPoint' => '']);

        self::assertTrue($form->isValid());
        self::assertSame(['title' => 'About us', 'slug' => 'about-us', 'startingPoint' => null], $form->getData());
    }

    /**
     * @test
     */
    public function it_picks_blank_to_start_with_when_the_default_starting_point_is_disabled(): void
    {
        $form = $this->factory->create(CreateEntryType::class, null, ['starting_points' => [self::startingPoint('landing', default: true, disabled: true)]]);

        /** @var array{data: string|null} $vars */
        $vars = $form->createView()['startingPoint']->vars;
        self::assertNull($vars['data']);
    }

    /**
     * @test
     */
    public function it_disables_a_starting_point_with_blocks_of_removed_types_and_refuses_it(): void
    {
        $form = $this->factory->create(CreateEntryType::class, null, ['starting_points' => [self::startingPoint('countdown', disabled: true)]]);

        /** @var array{choices: list<ChoiceView>} $vars */
        $vars = $form->createView()['startingPoint']->vars;
        self::assertSame(['data-description' => 'About countdown', 'disabled' => 'disabled'], $vars['choices'][0]->attr);

        $form->submit(['title' => 'About us', 'slug' => 'about-us', 'startingPoint' => 'countdown']);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('startingPoint')->getErrors());
        self::assertSame('setono_sylius_cms.starting_point.disabled', $form->get('startingPoint')->getErrors()[0]->getMessageTemplate());
    }

    /**
     * @test
     */
    public function it_refuses_a_starting_point_it_does_not_offer(): void
    {
        $form = $this->factory->create(CreateEntryType::class, null, ['starting_points' => [self::startingPoint('landing')]]);

        $form->submit(['title' => 'About us', 'slug' => 'about-us', 'startingPoint' => 'gone']);

        self::assertFalse($form->isValid());
    }

    /**
     * @test
     */
    public function it_has_no_starting_point_to_pick_for_a_content_type_without_any(): void
    {
        self::assertFalse($this->factory->create(CreateEntryType::class)->has('startingPoint'));
    }

    /**
     * @test
     */
    public function its_fields_have_translated_labels_and_a_block_prefix(): void
    {
        $view = $this->factory->create(CreateEntryType::class)->createView();

        /** @var array{label: string, required: bool, attr: array<string, string>} $title */
        $title = $view['title']->vars;
        /** @var array{label: string, required: bool, attr: array<string, string>, help: string} $slug */
        $slug = $view['slug']->vars;
        /** @var array{name: string} $form */
        $form = $view->vars;

        self::assertSame('setono_sylius_cms.form.entry.title', $title['label']);
        self::assertTrue($title['required']);
        self::assertSame(['maxlength' => 255], $title['attr']);
        self::assertSame('setono_sylius_cms.form.entry.slug', $slug['label']);
        self::assertSame('setono_sylius_cms.form.entry.slug_help', $slug['help']);
        // The server fills an empty slug from the title, so the browser lets it be empty
        self::assertFalse($slug['required']);
        self::assertSame(['maxlength' => 255, 'pattern' => '(?!/)(?!.*//)(?!.*/$).+', 'title' => 'setono_sylius_cms.form.entry.slug_rules'], $slug['attr']);
        self::assertSame('setono_sylius_cms_create_entry', $form['name']);
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidData
     *
     * @param array<string, string> $data
     */
    public function it_refuses_invalid_data(array $data, string $field): void
    {
        $form = $this->factory->create(CreateEntryType::class);

        $form->submit($data + ['title' => 'About us', 'slug' => 'about-us']);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get($field)->getErrors());
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function provideInvalidData(): iterable
    {
        yield 'an empty title' => [['title' => ''], 'title'];
        yield 'a title longer than 255 characters' => [['title' => str_repeat('a', 256)], 'title'];
        yield 'a slug longer than 255 characters' => [['slug' => str_repeat('a', 256)], 'slug'];
        yield 'a slug starting with a slash' => [['slug' => '/about'], 'slug'];
        yield 'a slug ending with a slash' => [['slug' => 'about/'], 'slug'];
        yield 'a slug with two slashes in a row' => [['slug' => 'about//team'], 'slug'];
        yield 'a title the slug generator makes nothing of' => [['title' => '!!!', 'slug' => ''], 'slug'];
    }

    /**
     * @test
     */
    public function it_accepts_255_characters(): void
    {
        $form = $this->factory->create(CreateEntryType::class);

        $form->submit(['title' => str_repeat('a', 255), 'slug' => str_repeat('a', 255)]);

        self::assertTrue($form->isValid());
    }

    /**
     * @test
     */
    public function it_refuses_a_layouts_option_that_is_not_an_array(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(CreateEntryType::class, null, ['layouts' => 'standard']);
    }

    /**
     * @test
     */
    public function its_slug_option_is_a_boolean(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(CreateEntryType::class, null, ['slug' => 'yes']);
    }

    private static function startingPoint(string $id, bool $default = false, bool $disabled = false, ?string $thumbnail = null): StartingPointChoice
    {
        $preset = str_contains($id, '-')
            ? new Preset($id, [], startingPoint: new StartingPoint('page', null), uuid: $id)
            : new Preset($id, [], startingPoint: new StartingPoint('page', null, $default), code: $id, thumbnail: $thumbnail);

        return new StartingPointChoice($preset, 'The ' . $id, 'About ' . $id, $thumbnail, $default, [], $disabled);
    }

    /**
     * @return list<FormExtensionInterface>
     */
    protected function getExtensions(): array
    {
        // The tests pick the store's channels after the factory is built, before they build the form
        $storeChannels = &$this->channels;
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy([], ['name' => 'ASC'])->will(static function () use (&$storeChannels): array {
            return $storeChannels;
        });

        return [
            new PreloadedExtension([new CreateEntryType(new SlugGenerator(), $channels->reveal())], []),
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    /**
     * @param list<ChoiceView> $choices
     *
     * @return array<string, string> value => label
     */
    private static function labels(array $choices): array
    {
        $labels = [];
        foreach ($choices as $choice) {
            self::assertIsString($choice->value);
            self::assertIsString($choice->label);
            $labels[$choice->value] = $choice->label;
        }

        return $labels;
    }

    private static function channel(?string $code, ?string $name): ChannelInterface
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);

        return $channel;
    }
}

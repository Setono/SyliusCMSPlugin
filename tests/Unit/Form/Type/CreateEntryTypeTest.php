<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Form\Type;

use Setono\SyliusCMSPlugin\Form\Type\CreateEntryType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Validation;

final class CreateEntryTypeTest extends TypeTestCase
{
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
    public function its_fields_have_translated_labels_and_a_block_prefix(): void
    {
        $view = $this->factory->create(CreateEntryType::class)->createView();

        /** @var array{label: string} $title */
        $title = $view['title']->vars;
        /** @var array{label: string} $slug */
        $slug = $view['slug']->vars;
        /** @var array{name: string} $form */
        $form = $view->vars;

        self::assertSame('setono_sylius_cms.form.entry.title', $title['label']);
        self::assertSame('setono_sylius_cms.form.entry.slug', $slug['label']);
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
    public function provideInvalidData(): iterable
    {
        yield 'an empty title' => [['title' => ''], 'title'];
        yield 'a title longer than 255 characters' => [['title' => str_repeat('a', 256)], 'title'];
        yield 'an empty slug' => [['slug' => ''], 'slug'];
        yield 'a slug longer than 255 characters' => [['slug' => str_repeat('a', 256)], 'slug'];
        yield 'a slug starting with a slash' => [['slug' => '/about'], 'slug'];
        yield 'a slug ending with a slash' => [['slug' => 'about/'], 'slug'];
        yield 'a slug with two slashes in a row' => [['slug' => 'about//team'], 'slug'];
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
    public function its_slug_option_is_a_boolean(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(CreateEntryType::class, null, ['slug' => 'yes']);
    }

    /**
     * @return list<ValidatorExtension>
     */
    protected function getExtensions(): array
    {
        return [new ValidatorExtension(Validation::createValidator())];
    }
}

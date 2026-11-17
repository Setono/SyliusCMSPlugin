<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistryInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Asks every context dependency finder what depends on an entry's host-page context, and holds it against what the
 * entry provides, or against a change of what a snippet declares. A context key is named by its label, or by itself
 * when the code no longer declares it
 *
 * @internal
 */
final readonly class ContextCheck implements ContextCheckInterface
{
    /** The entry field a snippet declares its context in, which errors about placements are in */
    public const FIELD = 'context';

    /**
     * @param iterable<ContextDependencyFinderInterface> $finders
     */
    public function __construct(
        private iterable $finders,
        private ContextKeyRegistryInterface $contextKeys,
        private TranslatorInterface $translator,
    ) {
    }

    public function mismatches(EntryInterface $workingCopy): array
    {
        $provided = EntryContext::keys($workingCopy);

        $errors = [];
        foreach ($this->dependencies($workingCopy) as $dependency) {
            $missing = Needs::missing($dependency->needs, $provided);
            if ([] !== $missing) {
                $errors[] = new ValidationError($dependency->block, null === $dependency->block ? self::FIELD : null, null, $this->message('setono_sylius_cms.context.needs', $dependency, $missing));
            }

            $unprovided = null === $dependency->provides ? [] : Needs::missing($provided, $dependency->provides);
            if ([] !== $unprovided) {
                $errors[] = new ValidationError($dependency->block, null === $dependency->block ? self::FIELD : null, null, $this->message('setono_sylius_cms.context.does_not_provide', $dependency, $unprovided));
            }
        }

        return $errors;
    }

    public function blockers(EntryInterface $workingCopy, array $from, array $to): array
    {
        $removed = Needs::missing($from, $to);
        $added = Needs::missing($to, $from);
        if ([] === $removed && [] === $added) {
            return [];
        }

        $errors = [];
        foreach ($this->dependencies($workingCopy) as $dependency) {
            $needed = array_values(array_intersect($dependency->needs, $removed));
            if ([] !== $needed) {
                $errors[] = new ValidationError(null, self::FIELD, null, $this->message('setono_sylius_cms.context.needs', $dependency, $needed));
            }

            $unprovided = null === $dependency->provides ? [] : Needs::missing($added, $dependency->provides);
            if ([] !== $unprovided) {
                $errors[] = new ValidationError(null, self::FIELD, null, $this->message('setono_sylius_cms.context.does_not_provide', $dependency, $unprovided));
            }
        }

        return $errors;
    }

    /**
     * @return \Generator<ContextDependency>
     */
    private function dependencies(EntryInterface $workingCopy): \Generator
    {
        foreach ($this->finders as $finder) {
            yield from $finder->findIn($workingCopy);
        }
    }

    /**
     * @param list<string> $keys
     */
    private function message(string $message, ContextDependency $dependency, array $keys): string
    {
        return (new TranslatableMessage($message, [
            '%what%' => $dependency->name,
            '%context%' => implode(', ', array_map(fn (string $key): string => sprintf('"%s"', $this->label($key)), $keys)),
        ], 'validators'))->trans($this->translator);
    }

    private function label(string $key): string
    {
        return $this->contextKeys->has($key) ? $this->translator->trans($this->contextKeys->get($key)->label) : $key;
    }
}

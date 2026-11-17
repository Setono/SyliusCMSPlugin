<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the host-page context a slot provides: each key it's passed an object of the key's class for, and the
 * others through their context resolvers, which get the main request, also inside a sub-request such as Sylius's taxon
 * header. What a resolver finds is reused by every slot on the page, until the next main request. A key that can't be
 * resolved, such as the product on a page that isn't a product page, resolves nothing at all.
 * See "Context resolvers" in docs/spec/slots.md and "Rendering a slot" in docs/spec/rendering.md
 *
 * @internal
 */
final class HostPageContextResolver
{
    /** @var \WeakMap<Request, array<string, object|null>> what each resolver found, by main request and key */
    private \WeakMap $resolved;

    /**
     * @param ContainerInterface $resolvers the context resolvers by key, which RegisterContextResolversPass hands it
     */
    public function __construct(
        private readonly ContextKeyRegistryInterface $contextKeys,
        private readonly ContainerInterface $resolvers,
        private readonly RequestStack $requestStack,
    ) {
        $this->resolved = new \WeakMap();
    }

    /**
     * @param list<string> $keys the context keys to resolve, such as those a slot provides
     * @param array<array-key, mixed> $passed what the slot is passed, such as its template event's context
     *
     * @return array<string, object>|null the object of each key, or null when one can't be resolved
     */
    public function resolve(array $keys, array $passed): ?array
    {
        $context = [];
        foreach ($keys as $key) {
            $object = $passed[$key] ?? null;
            if (!$this->contextKeys->get($key)->holds($object)) {
                $object = $this->resolved($key);
            }

            if (!is_object($object)) {
                return null;
            }

            $context[$key] = $object;
        }

        return $context;
    }

    private function resolved(string $key): ?object
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request || !$this->resolvers->has($key)) {
            return null;
        }

        $resolved = $this->resolved[$request] ?? [];
        if (!array_key_exists($key, $resolved)) {
            $resolver = $this->resolvers->get($key);
            if (!$resolver instanceof ContextResolverInterface) {
                throw new \LogicException(sprintf('The context resolver of the key "%s" must implement %s', $key, ContextResolverInterface::class));
            }

            $object = $resolver->resolve($request);
            $contextKey = $this->contextKeys->get($key);
            if (null !== $object && !$contextKey->holds($object)) {
                throw new \UnexpectedValueException(sprintf('The context resolver of the key "%s" must find a %s or null, but found a %s', $key, $contextKey->class, $object::class));
            }

            $resolved[$key] = $object;
            $this->resolved[$request] = $resolved;
        }

        return $resolved[$key];
    }
}

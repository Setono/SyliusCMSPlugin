<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Psr\Container\ContainerInterface;

/**
 * Finds the handler service a field attribute names
 */
final readonly class FieldHandlerLocator
{
    /**
     * @param ContainerInterface $handlers the field type handlers, keyed by service id
     */
    public function __construct(private ContainerInterface $handlers)
    {
    }

    public function get(Field $field): FieldHandlerInterface
    {
        $id = $field->handledBy();

        if (!$this->handlers->has($id)) {
            throw new \LogicException(sprintf('The field %s is handled by the service "%s", which isn\'t registered as a field type handler. Make it implement %s and autoconfigure it', $field::class, $id, FieldHandlerInterface::class));
        }

        $handler = $this->handlers->get($id);
        if (!$handler instanceof FieldHandlerInterface) {
            throw new \LogicException(sprintf('The service "%s" must implement %s', $id, FieldHandlerInterface::class));
        }

        return $handler;
    }
}

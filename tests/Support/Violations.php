<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Prophecy\Argument;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Expects a field handler's or a validator's validation to add one violation. For a test case using ProphecyTrait
 */
trait Violations
{
    /**
     * @param array<string, string>|null $parameters the violation's parameters, or null for any
     * @param \Closure(ExecutionContextInterface): void $validate
     */
    private function expectViolation(string $message, ?array $parameters, \Closure $validate): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        if (null === $parameters) {
            $builder->setParameter(Argument::cetera())->willReturn($builder->reveal());
        } else {
            foreach ($parameters as $name => $value) {
                $builder->setParameter($name, $value)->willReturn($builder->reveal())->shouldBeCalledOnce();
            }
        }
        $builder->setPlural(Argument::any())->willReturn($builder->reveal());
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation($message)->willReturn($builder->reveal())->shouldBeCalledOnce();
        $context->buildViolation(Argument::not($message))->shouldNotBeCalled();

        $validate($context->reveal());
    }
}

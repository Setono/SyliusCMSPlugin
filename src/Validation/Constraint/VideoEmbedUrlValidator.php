<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation\Constraint;

use Setono\SyliusCMSPlugin\Media\Video\VideoEmbed;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class VideoEmbedUrlValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof VideoEmbedUrl) {
            throw new UnexpectedTypeException($constraint, VideoEmbedUrl::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (null === VideoEmbed::fromUrl($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}

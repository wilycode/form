<?php

declare(strict_types=1);

namespace WilyCode\Form\Validators;

use WilyCode\Validator\Validator;
use WilyCode\Form\Options;

class InOptionsValidator extends Validator
{
    private const string INVALID_OPTION = 'invalidOption';

    private Options $options;

    /** @param array<int|string, mixed> $options */
    public function __construct(
        array $options,
        private readonly bool $strict = false,
    ) {
        $this->options = new Options($options);
    }

    public function isValid(mixed $value): bool
    {
        $this->clearErrors();

        if (is_array($value)) {
            foreach ($value as $single) {
                if (!$this->options->contains($single, $this->strict)) {
                    $this->addError(self::INVALID_OPTION);
                    return false;
                }
            }

            return true;
        }

        if (!$this->options->contains($value, $this->strict)) {
            $this->addError(self::INVALID_OPTION);
            return false;
        }

        return true;
    }
}

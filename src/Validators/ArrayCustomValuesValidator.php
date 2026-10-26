<?php

declare(strict_types=1);

namespace WilyCode\Form\Validators;

use WilyCode\Form\Options;
use WilyCode\Validator\Validator;
use WilyCode\Validator\ValidatorInterface;

class ArrayCustomValuesValidator extends Validator implements OptionsAwareValidatorInterface
{
    private const string INVALID = 'invalidType';

    /** @var list<ValidatorInterface> */
    private array $validators;

    private Options $options;

    public function __construct(ValidatorInterface ...$validators)
    {
        $this->validators = $validators;
        $this->options = new Options([]);
    }

    /** @param array<int|string, mixed>|null $options */
    public function setOptions(?array $options): static
    {
        $this->options = new Options($options ?? []);
        return $this;
    }

    public function isValid(mixed $value): bool
    {
        $this->clearErrors();

        if (!is_array($value)) {
            $this->addError(self::INVALID);
            return false;
        }

        $isValid = true;

        foreach ($this->options->custom($value) as $index => $item) {
            foreach ($this->validators as $validator) {
                $validator->clearErrors();

                if (!$validator->isValid($item)) {
                    $this->getErrorBag()
                        ->getOrCreateItem($index)
                        ->merge($validator->getErrorBag());
                    $isValid = false;

                    if ($validator->isStop()) {
                        break;
                    }
                }
            }
        }

        return $isValid;
    }

    /** @return list<ValidatorInterface> */
    public function getValidators(): array
    {
        return $this->validators;
    }

    public function __clone(): void
    {
        $this->validators = array_map(
            static fn(ValidatorInterface $validator): ValidatorInterface => clone $validator,
            $this->validators,
        );

        $this->clearErrors();
    }
}

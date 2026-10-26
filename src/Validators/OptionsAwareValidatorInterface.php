<?php

declare(strict_types=1);

namespace WilyCode\Form\Validators;

interface OptionsAwareValidatorInterface
{
    /** @param array<int|string, mixed>|null $options */
    public function setOptions(?array $options): static;
}

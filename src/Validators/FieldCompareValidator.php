<?php

declare(strict_types=1);

namespace WilyCode\Form\Validators;

use WilyCode\Validator\Validator;
use WilyCode\Form\GetterInterface;

class FieldCompareValidator extends Validator
{
    private const string MISMATCH = 'mismatch';

    private GetterInterface $context;
    private string $fieldName;
    private string $fieldLabel;

    public function __construct(GetterInterface $context, string $fieldName, string $fieldLabel)
    {
        $this->context = $context;
        $this->fieldName = $fieldName;
        $this->fieldLabel = $fieldLabel;
    }

    /** @internal Repoint a comparison to a cloned fieldset/collection row. */
    public function rebindContext(GetterInterface $original, GetterInterface $replacement): void
    {
        if ($this->context === $original) {
            $this->context = $replacement;
        }
    }

    public function isValid(mixed $value): bool
    {
        $this->clearErrors();

        if ($this->context->getField($this->fieldName)->getData() !== $value) {
            $this->addError(self::MISMATCH, ['fieldLabel' => $this->fieldLabel]);
            return false;
        }

        return true;
    }
}
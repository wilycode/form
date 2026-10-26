<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Field;
use WilyCode\Validators\StringLengthValidator;

final class FieldValidationResetTest extends TestCase
{
    public function test_field_errors_are_reset_between_validations(): void
    {
        $field = new Field('title', true)
            ->addValidator(new StringLengthValidator()->setMin(3));

        $field->setData([]);
        $this->assertFalse($field->isValid());
        $this->assertTrue($field->getErrors()->hasErrors());

        $field->setData(['title' => 'abcd']);
        $this->assertTrue($field->isValid());
        $this->assertFalse($field->getErrors()->hasErrors());
    }
}

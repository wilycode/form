<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Validator\ErrorBag;
use WilyCode\Form\Field;
use WilyCode\Filters\ArrayItemsFilter;
use WilyCode\Filters\TrimFilter;
use WilyCode\Validators\ArrayItemsValidator;
use WilyCode\Validators\ArrayLengthValidator;
use WilyCode\Validators\StringLengthValidator;

final class FieldMultipleValidationTest extends TestCase
{
    public function test_add_validator_validates_whole_multiple_field_value(): void
    {
        $field = new Field('authors')
            ->setEmptyValue([])
            ->multiple()
            ->addValidator(new ArrayLengthValidator()->setMin(1));

        $field->setData(['authors' => ['1', '2']]);

        $this->assertTrue($field->isValid());
        $this->assertFalse($field->getErrors()->hasErrors());
    }

    public function test_array_items_validator_stores_errors_under_item_indexes(): void
    {
        $field = new Field('tags')
            ->setEmptyValue([])
            ->multiple()
            ->addValidator(
                new ArrayItemsValidator(
                    new StringLengthValidator()->setMin(2)->setStop(true),
                ),
            );

        $field->setData(['tags' => ['a', 'bc']]);

        $this->assertFalse($field->isValid());

        $errors = $field->getErrors();
        $this->assertInstanceOf(ErrorBag::class, $errors);
        $this->assertTrue($errors->hasItem(0));
        $this->assertFalse($errors->hasItem(1));
        $this->assertSame('validation.string_length.too_short', $errors->getItem(0)?->getErrors()[0]->getCode());
    }

    public function test_array_items_filter_filters_each_multiple_value(): void
    {
        $field = new Field('tags')
            ->setEmptyValue([])
            ->multiple()
            ->addFilter(new ArrayItemsFilter(new TrimFilter()));

        $field->setData(['tags' => ['  first ', ' second  ']]);

        $this->assertSame(['first', 'second'], $field->getData());
    }
}

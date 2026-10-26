<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Validator\ErrorBag;
use WilyCode\Form\Field;
use WilyCode\Filters\ArrayItemsFilter;
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Validators\ArrayCustomValuesValidator;
use WilyCode\Validators\StringLengthValidator;

final class CustomValuesValidationTest extends TestCase
{
    public function test_custom_values_use_normal_filter_and_validator_pipeline(): void
    {
        $tags = new Field('tags')
            ->setEmptyValue([])
            ->multiple()
            ->allowCustomValues()
            ->options(['a', 'b', 'c'])
            ->addFilter(new ArrayItemsFilter(new TrimFilter()))
            ->addValidator(
                new ArrayCustomValuesValidator(
                    new StringLengthValidator()->setMin(2)->setMax(3),
                ),
            );

        $tags->setData(['tags' => ['  ab  ', 'c']]);

        $this->assertSame(['c'], $tags->getSelectedOptions());
        $this->assertSame(['ab'], $tags->getCustomValues());
        $this->assertTrue($tags->isValid());
    }

    public function test_custom_value_validator_reports_errors_per_item_index(): void
    {
        $tags = new Field('tags')
            ->setEmptyValue([])
            ->multiple()
            ->allowCustomValues()
            ->options(['a', 'b'])
            ->addValidator(
                new ArrayCustomValuesValidator(
                    new StringLengthValidator()->setMin(2)->setMax(3)->setStop(true),
                ),
            );

        $tags->setData(['tags' => ['a', 'abcd']]);
        $this->assertFalse($tags->isValid());

        $errors = $tags->getErrors();
        $this->assertInstanceOf(ErrorBag::class, $errors);
        $this->assertFalse($errors->hasItem(0));
        $this->assertTrue($errors->hasItem(1));
        $this->assertSame('validation.string_length.too_long', $errors->getItem(1)?->getErrors()[0]->getCode());
    }

    public function test_options_added_after_validator_are_rebound_automatically(): void
    {
        $tags = new Field('tags')
            ->setEmptyValue([])
            ->multiple()
            ->allowCustomValues()
            ->addValidator(
                new ArrayCustomValuesValidator(
                    new StringLengthValidator()->setMin(2),
                ),
            )
            ->options(['a', 'b']);

        $tags->setData(['tags' => ['a']]);

        $this->assertTrue($tags->isValid());
    }
}

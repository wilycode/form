<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Validator\ErrorBag;
use WilyCode\Form\Field;

final class FieldOptionsTest extends TestCase
{
    public function test_selected_and_new_options_for_list_options(): void
    {
        $field = new Field('status')
            ->options(['a', 'b', 'c']);

        $field->setData(['status' => 'b']);
        $this->assertSame(['b'], $field->getSelectedOptions());
        $this->assertSame([], $field->getCustomValues());

        $field->setData(['status' => 'x']);
        $this->assertSame([], $field->getSelectedOptions());
        $this->assertSame(['x'], $field->getCustomValues());

        $field->multiple()->setEmptyValue([]);
        $field->setData(['status' => ['a', 'x', 'c']]);
        $this->assertSame(['a', 'c'], $field->getSelectedOptions());
        $this->assertSame(['x'], $field->getCustomValues());
        $this->assertSame([1 => 'x'], $field->getCustomValuesWithIndexes());
    }

    public function test_selected_and_new_options_for_associative_options(): void
    {
        $field = new Field('role')
            ->options(['1' => 'User', '2' => 'Admin']);

        $field->setData(['role' => '2']);
        $this->assertSame(['2'], $field->getSelectedOptions());
        $this->assertSame([], $field->getCustomValues());

        $field->setData(['role' => 'Admin']);
        $this->assertSame([], $field->getSelectedOptions());
        $this->assertSame(['Admin'], $field->getCustomValues());
    }

    public function test_in_options_validator_accepts_associative_option_keys(): void
    {
        $field = new Field('role')
            ->options(['1' => 'User', '2' => 'Admin'])
            ->required();

        $field->setData(['role' => '1']);
        $this->assertTrue($field->isValid());

        $field->setData(['role' => '3']);
        $this->assertFalse($field->isValid());
        $errors = $field->getErrors();
        $this->assertInstanceOf(ErrorBag::class, $errors);
        $this->assertNotEmpty($errors->getErrors());
        $this->assertSame('validation.in_options.invalid_option', $errors->getErrors()[0]->getCode());
    }

    public function test_required_option_field_returns_required_error_before_invalid_option(): void
    {
        $field = new Field('category')
            ->options(['news' => 'News', 'guides' => 'Guides']);

        $field->setData(['category' => '']);

        $this->assertFalse($field->isValid());
        $this->assertSame('validation.not_empty.required', $field->getErrors()->getErrors()[0]->getCode());
        $this->assertCount(1, $field->getErrors()->getErrors());
    }

    public function test_optional_option_field_accepts_empty_value(): void
    {
        $field = new Field('category', required: false)
            ->options(['news' => 'News', 'guides' => 'Guides']);

        $field->setData(['category' => '']);

        $this->assertTrue($field->isValid());
        $this->assertFalse($field->getErrors()->hasErrors());
    }

}

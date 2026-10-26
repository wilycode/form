<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Filter\FilterInterface;
use WilyCode\Form\Field;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Options;
use WilyCode\Form\Validators\InOptionsValidator;

final class OptionMatchingAndEmptyGroupFiltersTest extends TestCase
{
    public function testArbitraryStringsDoNotMatchBooleanOptions(): void
    {
        $field = (new Field('enabled'))->options([false, true]);

        foreach (['arbitrary', 'yes', 'maybe', '2', 'on'] as $input) {
            $field->setData(['enabled' => $input]);

            self::assertFalse($field->isValid(), 'Unexpected boolean option accepted: ' . $input);
            self::assertSame('validation.in_options.invalid_option', $field->getErrors()->getErrors()[0]->getCode());
            self::assertSame([], $field->getSelectedOptions());
            self::assertSame([$input], $field->getCustomValues());
        }
    }

    public function testExplicitBooleanRepresentationsCanBeSubmittedByHtml(): void
    {
        $field = (new Field('enabled'))->options([false, true]);

        foreach (['0', '1', 'false', 'true', 0, 1, false, true] as $input) {
            $field->setData(['enabled' => $input]);

            self::assertTrue($field->isValid(), 'Explicit boolean option rejected: ' . var_export($input, true));
            self::assertSame([$input], $field->getSelectedOptions());
            self::assertSame([], $field->getCustomValues());
        }
    }

    public function testStrictMatchingAndNumericStringOptionsRemainPredictable(): void
    {
        $numeric = (new Field('choice'))->options([1, 2]);
        $numeric->setData(['choice' => '1']);

        self::assertTrue($numeric->isValid());
        self::assertSame(['1'], $numeric->getSelectedOptions());
        self::assertSame([], $numeric->getCustomValues());

        self::assertFalse((new InOptionsValidator([1, 2], strict: true))->isValid('1'));
        self::assertTrue((new InOptionsValidator([1, 2], strict: true))->isValid(1));
        self::assertFalse((new Options([false, true]))->contains('arbitrary', strict: false));
        self::assertFalse((new Options([false, true]))->contains('1', strict: true));
    }

    public function testDistinctNumericLookingStringOptionsNeverMatchLoosely(): void
    {
        $options = new Options(['0e123456']);
        self::assertFalse($options->contains('0e777777', strict: false));
        self::assertTrue($options->contains('0e123456', strict: false));

        $field = (new Field('selection'))->options(['0e123456']);
        $field->setData(['selection' => '0e777777']);
        self::assertFalse($field->isValid());
        self::assertSame(['0e777777'], $field->getCustomValues());

        $looseValidator = new InOptionsValidator(['1', '0e123456']);
        self::assertFalse($looseValidator->isValid('0e777777'));
        self::assertTrue($looseValidator->isValid('0e123456'));

        // Numeric options remain usable with their HTML string equivalents.
        $numericOptions = new Options([0, 1, 2]);
        self::assertTrue($numericOptions->contains('1', strict: false));
        foreach (['0e777', '01', '1e0', '1.0', ' 1'] as $nonCanonical) {
            self::assertFalse($numericOptions->contains($nonCanonical, strict: false));
        }
    }

    public function testGroupFilterRunsOnExplicitlySubmittedEmptyFieldset(): void
    {
        $fieldset = new Fieldset('profile');
        $fieldset->addField('name');
        $fieldset->addFilter(new class implements FilterInterface {
            public function getFilteredValue(mixed $value): mixed
            {
                return ['name' => 'Default'] + $value;
            }
        });

        $fieldset->setData(['profile' => []]);

        self::assertSame(['name' => 'Default'], $fieldset->getData());
        self::assertTrue($fieldset->isValid());
    }

    public function testUnchangedEmptyArrayRetainsExistingFieldsetBehavior(): void
    {
        $fieldset = new Fieldset('profile', required: false);
        $fieldset->addField('name');
        $fieldset->addFilter(new class implements FilterInterface {
            public function getFilteredValue(mixed $value): mixed
            {
                return $value;
            }
        });

        $fieldset->setData(['profile' => []]);

        self::assertSame([], $fieldset->getData());
        self::assertTrue($fieldset->isValid());
    }

    public function testInvalidGroupFilterResultFromEmptyArrayFailsValidation(): void
    {
        $fieldset = new Fieldset('profile', required: false);
        $fieldset->addField('name');
        $fieldset->addFilter(new class implements FilterInterface {
            public function getFilteredValue(mixed $value): mixed
            {
                return 'not-an-array';
            }
        });

        $fieldset->setData(['profile' => []]);

        self::assertFalse($fieldset->isValid());
        self::assertSame('validation.array.invalid_type', $fieldset->getGeneralErrors()[0]->getCode());
    }
}

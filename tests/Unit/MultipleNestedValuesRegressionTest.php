<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Field;
use WilyCode\Form\Fieldset;

final class MultipleNestedValuesRegressionTest extends TestCase
{
    public function testNestedArraysAreRejectedForRequiredMultipleFields(): void
    {
        $field = (new Field('tags'))->multiple();

        foreach ([[[]], [['abc']], ['valid', []]] as $values) {
            $field->setData(['tags' => $values]);

            self::assertFalse($field->isValid());
            self::assertSame('validation.array.invalid_type', $field->getErrors()->getErrors()[0]->getCode());
        }
    }

    public function testNestedArraysAreRejectedForOptionalMultipleFields(): void
    {
        $field = (new Field('tags', required: false))->multiple();
        $field->setData(['tags' => [[]]]);

        self::assertFalse($field->isValid());
        self::assertSame('validation.array.invalid_type', $field->getErrors()->getErrors()[0]->getCode());
    }

    public function testOptionalFieldsetCannotHideMalformedNestedSelections(): void
    {
        $profile = new Fieldset('profile', required: false);
        $profile->addField('tags')->multiple();
        $profile->setData(['profile' => ['tags' => [[]]]]);

        self::assertFalse($profile->isValid());
        self::assertSame(
            'validation.array.invalid_type',
            $profile->getErrors()['tags']->getErrors()[0]->getCode(),
        );
    }

    public function testFlatSelectionsRemainValid(): void
    {
        $field = (new Field('tags'))->multiple();

        foreach ([['abc'], ['0'], [0], ['alpha', 'beta']] as $values) {
            $field->setData(['tags' => $values]);
            self::assertTrue($field->isValid());
        }
    }

    public function testMissingOrEmptyOptionalSelectionsRemainValid(): void
    {
        $field = (new Field('tags', required: false))->multiple();

        foreach ([[], [''], [null]] as $values) {
            $field->setData(['tags' => $values]);
            self::assertTrue($field->isValid());
        }
    }
}

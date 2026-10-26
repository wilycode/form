<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Form\Field;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;
use WilyCode\Validator\Error;
use WilyCode\Validator\Validator;

final class ValidationStateResetTest extends TestCase
{
    public function testFieldClearsGeneratedErrorsOnNewDataEvenBeforeRevalidation(): void
    {
        $validator = new class extends Validator {
            public function isValid(mixed $value): bool
            {
                $this->addError('rejected');
                return false;
            }
        };

        $field = (new Field('title'))->addValidator($validator);
        $field->setData(['title' => 'old']);
        self::assertFalse($field->isValid());
        self::assertTrue($field->getErrors()->hasErrors());
        self::assertNotEmpty($validator->getErrors());

        $field->setData(['title' => 'new']);
        self::assertFalse($field->getErrors()->hasErrors());
        self::assertSame([], $validator->getErrors());
    }

    public function testFormClearsPreviousFieldAndGeneralErrorsOnNewData(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addField('title');
            }
        };

        $form->setData(['title' => '']);
        self::assertFalse($form->isValid());
        self::assertNotEmpty($form->getErrors());

        $form->addGeneralError(new Error('validation.external.rejected'));
        $form->setData(['title' => 'ready']);
        self::assertSame([], $form->getErrors());
        self::assertSame([], $form->getGeneralErrors());
        self::assertFalse($form->getField('title')->getErrors()->hasErrors());
        self::assertTrue($form->isValid());
    }

    public function testFieldsetClearsPreviousErrorsOnNewData(): void
    {
        $fieldset = new Fieldset('profile');
        $fieldset->addField('name');

        $fieldset->setData(['profile' => ['name' => '']]);
        self::assertFalse($fieldset->isValid());
        self::assertNotEmpty($fieldset->getErrors());

        $fieldset->setData(['profile' => ['name' => 'Ada']]);
        self::assertSame([], $fieldset->getErrors());
        self::assertSame([], $fieldset->getGeneralErrors());
        self::assertTrue($fieldset->isValid());
    }

    public function testCollectionClearsPreviousErrorsOnNewData(): void
    {
        $collection = new Collection('items');
        $collection->addField('name');

        $collection->setData(['items' => [['name' => '']]]);
        self::assertFalse($collection->isValid());
        self::assertNotEmpty($collection->getErrors());

        $collection->setData(['items' => [['name' => 'Ada']]]);
        self::assertSame([], $collection->getErrors());
        self::assertSame([], $collection->getGeneralErrors());
        self::assertTrue($collection->isValid());
    }
}

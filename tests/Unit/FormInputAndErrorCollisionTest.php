<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Form\Field;
use WilyCode\Form\Form;
use WilyCode\Validator\Error;
use WilyCode\Validator\Validator;
use WilyCode\Validators\ArrayLengthValidator;

final class RootAndFirstItemValidator extends Validator
{
    public function isValid(mixed $value): bool
    {
        $this->getErrorBag()->addError(new Error('root_error'));
        $this->getErrorBag()->getOrCreateItem(0)->addError(new Error('item_error'));
        return false;
    }
}

final class FormInputAndErrorCollisionTest extends TestCase
{
    public function testMultipleFieldRejectsNonEmptyScalarAndRecoversOnNextSubmission(): void
    {
        $field = (new Field('tags'))->multiple();
        $field->setData(['tags' => 'admin']);
        self::assertFalse($field->isValid());
        self::assertSame('validation.array.invalid_type', $field->getErrors()->getErrors()[0]->getCode());

        $field->setData(['tags' => ['admin', 'editor']]);
        self::assertTrue($field->isValid());
        self::assertSame(['admin', 'editor'], $field->getData());
    }

    public function testOptionalMultipleFieldAlsoRejectsNonEmptyScalar(): void
    {
        $field = (new Field('tags', required: false))->multiple();
        $field->setData(['tags' => 'admin']);
        self::assertFalse($field->isValid());
        self::assertSame('validation.array.invalid_type', $field->getErrors()->getErrors()[0]->getCode());

        $field->setData(['tags' => null]);
        self::assertTrue($field->isValid());
    }

    public function testGroupPreservesRootAndNumberedItemErrors(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addValidator(new RootAndFirstItemValidator());
            }
        };

        $form->setData([]);
        self::assertFalse($form->isValid());
        $errors = $form->getGeneralErrors();
        self::assertSame('item_error', $errors[0]['errors'][0]->getCode());
        self::assertSame('root_error', $errors[1]->getCode());
    }

    public function testCollectionRootAndFirstRowErrorsDoNotCollide(): void
    {
        $collection = new Collection('items');
        $fieldset = $collection->addFieldset('meta');
        $fieldset->addField('name');
        $collection->addValidator(new ArrayLengthValidator()->setMin(2));

        $collection->setData(['items' => [['meta' => ['name' => 'Ada']]]]);
        self::assertFalse($collection->isValid());
        $errors = $collection->getGeneralErrors();
        self::assertArrayHasKey('meta', $errors[0]);
        self::assertInstanceOf(Error::class, $errors[1]);
        self::assertSame('validation.array_length.too_few', $errors[1]->getCode());

        $collection->setData(['items' => [
            ['meta' => ['name' => 'Ada']],
            ['meta' => ['name' => 'Bob']],
        ]]);
        self::assertTrue($collection->isValid());
    }
}

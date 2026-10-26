<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;
use WilyCode\Validator\Error;
use WilyCode\Validator\Validator;

final class NamedGroupErrorValidator extends Validator
{
    public function isValid(mixed $value): bool
    {
        $this->getErrorBag()->getOrCreateItem('profile')->addError(new Error('group_profile_error'));
        return false;
    }
}

final class IndexedGroupErrorValidator extends Validator
{
    public function isValid(mixed $value): bool
    {
        $this->getErrorBag()->getOrCreateItem(0)->addError(new Error('group_row_error'));
        return false;
    }
}

final class NestedGroupErrorMergeTest extends TestCase
{
    public function testFormPreservesGroupErrorWhenMatchingFieldsetIsValid(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addValidator(new NamedGroupErrorValidator());
                $this->addFieldset('profile')->addField('name');
            }
        };

        $form->setData(['profile' => ['name' => 'Ada']]);
        self::assertFalse($form->isValid());
        self::assertSame('group_profile_error', $form->getGeneralErrors()['profile']['errors'][0]->getCode());
    }

    public function testFormPreservesGroupAndMatchingFieldsetErrorsTogether(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addValidator(new NamedGroupErrorValidator());
                $this->addFieldset('profile')->addField('name');
            }
        };

        $form->setData(['profile' => []]);
        self::assertFalse($form->isValid());
        $errors = $form->getGeneralErrors()['profile'];
        self::assertSame('group_profile_error', $errors['errors'][0]->getCode());
        self::assertSame('validation.not_empty.required', $errors[0]->getCode());
    }

    public function testNestedFieldsetPreservesMatchingGroupError(): void
    {
        $root = new Fieldset('root');
        $root->addValidator(new NamedGroupErrorValidator());
        $root->addFieldset('profile')->addField('name');
        $root->setData(['root' => ['profile' => []]]);

        self::assertFalse($root->isValid());
        $errors = $root->getGeneralErrors()['profile'];
        self::assertSame('group_profile_error', $errors['errors'][0]->getCode());
        self::assertSame('validation.not_empty.required', $errors[0]->getCode());
    }

    public function testCollectionMergesNestedRowErrorAndValidatorError(): void
    {
        $collection = new Collection('items');
        $collection->addValidator(new IndexedGroupErrorValidator());
        $collection->addFieldset('profile')->addField('name');
        $collection->setData(['items' => [['profile' => []]]]);

        self::assertFalse($collection->isValid());
        $errors = $collection->getGeneralErrors()[0];
        self::assertSame('group_row_error', $errors['errors'][0]->getCode());
        self::assertSame('validation.not_empty.required', $errors['profile'][0]->getCode());
    }

    public function testInvalidScalarFieldsetReportsOnlyInvalidType(): void
    {
        $fieldset = new Fieldset('profile');
        $fieldset->addField('name');
        $fieldset->setData(['profile' => 'invalid']);
        self::assertFalse($fieldset->isValid());
        self::assertCount(1, $fieldset->getGeneralErrors());
        self::assertSame('validation.array.invalid_type', $fieldset->getGeneralErrors()[0]->getCode());
    }

    public function testInvalidScalarCollectionReportsOnlyInvalidType(): void
    {
        $collection = new Collection('items');
        $collection->addField('name');
        $collection->setData(['items' => 'invalid']);
        self::assertFalse($collection->isValid());
        self::assertCount(1, $collection->getGeneralErrors());
        self::assertSame('validation.array.invalid_type', $collection->getGeneralErrors()[0]->getCode());
    }
}

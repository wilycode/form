<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;
use WilyCode\Validator\Error;

final class RequiredGroupsAndManualErrorsTest extends TestCase
{
    public function testRequiredCollectionRejectsRowsWithOnlyEmptyOptionalFields(): void
    {
        $collection = new Collection('items');
        $collection->addField('note', required: false);
        $collection->setData(['items' => [['note' => ''], ['note' => null]]]);

        self::assertFalse($collection->isValid());
        self::assertSame('validation.not_empty.required', $collection->getGeneralErrors()[0]->getCode());

        $collection->setData(['items' => [['note' => ''], ['note' => 'valid']]]);
        self::assertTrue($collection->isValid());
        self::assertSame([], $collection->getGeneralErrors());
    }

    public function testRequiredCollectionChecksNestedFieldsetAndNestedCollection(): void
    {
        $collection = new Collection('items');
        $meta = $collection->addFieldset('meta', required: false);
        $nested = $meta->addCollection('tags', required: false);
        $nested->addField('value', required: false);

        $collection->setData(['items' => [['meta' => ['tags' => [['value' => '']]]]]]);
        self::assertFalse($collection->isValid());

        $collection->setData(['items' => [['meta' => ['tags' => [['value' => 'yes']]]]]]);
        self::assertTrue($collection->isValid());
    }

    public function testRequiredFieldsetDoesNotMistakeEmptyCollectionRowForData(): void
    {
        $fieldset = new Fieldset('profile');
        $rows = $fieldset->addCollection('items', required: false);
        $rows->addField('note', required: false);

        $fieldset->setData(['profile' => ['items' => [['note' => '']]]]);
        self::assertFalse($fieldset->isValid());
        self::assertSame('validation.not_empty.required', $fieldset->getGeneralErrors()[0]->getCode());

        $fieldset->setData(['profile' => ['items' => [['note' => 'ok']]]]);
        self::assertTrue($fieldset->isValid());
    }

    public function testManuallyAddedFormErrorPersistsAcrossValidationsButNotNewData(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addField('title');
            }
        };

        $form->setData(['title' => 'Ready']);
        $form->addGeneralError(new Error('validation.external.invalid'));

        self::assertFalse($form->isValid());
        self::assertSame('validation.external.invalid', $form->getGeneralErrors()[0]->getCode());
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getGeneralErrors());

        $form->setData(['title' => 'Updated']);
        self::assertTrue($form->isValid());
        self::assertSame([], $form->getGeneralErrors());
    }

    public function testManuallyAddedFieldsetErrorInvalidatesGroupAndResetsOnNewData(): void
    {
        $fieldset = new Fieldset('profile', required: false);
        $fieldset->addField('nickname', required: false);
        $fieldset->setData(['profile' => ['nickname' => 'Ada']]);
        $fieldset->addGeneralError(new Error('validation.external.invalid'));

        self::assertFalse($fieldset->isValid());
        self::assertFalse($fieldset->isValid());
        self::assertCount(1, $fieldset->getGeneralErrors());

        $fieldset->setData(['profile' => ['nickname' => 'Changed']]);
        self::assertTrue($fieldset->isValid());
    }

    public function testManuallyAddedCollectionErrorInvalidatesGroupAndResetsOnNewData(): void
    {
        $collection = new Collection('items', required: false);
        $collection->addField('title');
        $collection->setData(['items' => [['title' => 'Ada']]]);
        $collection->addGeneralError(new Error('validation.external.invalid'));

        self::assertFalse($collection->isValid());
        self::assertFalse($collection->isValid());
        self::assertCount(1, $collection->getGeneralErrors());

        $collection->setData(['items' => [['title' => 'Changed']]]);
        self::assertTrue($collection->isValid());
    }

    public function testGeneratedValidationErrorDoesNotPersistAfterNewValidData(): void
    {
        $collection = new Collection('items');
        $collection->addField('name');
        $collection->setData(['items' => [['name' => '']]]);
        self::assertFalse($collection->isValid());

        $collection->setData(['items' => [['name' => 'Ada']]]);
        self::assertTrue($collection->isValid());
        self::assertSame([], $collection->getGeneralErrors());
    }
}

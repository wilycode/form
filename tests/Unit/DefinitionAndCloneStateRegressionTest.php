<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Form\Field;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;
use WilyCode\Validator\Error;
use WilyCode\Validator\Validator;

final class CloneFailureValidator extends Validator
{
    public function isValid(mixed $value): bool
    {
        $this->addError('invalid');
        return false;
    }
}

final class DefinitionAndCloneStateRegressionTest extends TestCase
{
    public function testSetFieldsIndexesAnOrdinaryListByFieldName(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->setFields([new Field('title'), new Field('slug')]);
            }
        };

        $form->setData(['title' => 'A', 'slug' => 'a']);
        self::assertSame(['title' => 'A', 'slug' => 'a'], $form->getData());
        self::assertSame('title', $form->getField('title')->getName());
        self::assertSame(['title', 'slug'], array_keys($form->getFields()));
    }

    public function testSetFieldsetsAndCollectionsIndexByTheirNames(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $profile = new Fieldset('profile');
                $profile->addField('name');
                $this->setFieldsets([$profile]);

                $items = new Collection('items');
                $items->addField('label');
                $this->setCollections([$items]);
            }
        };

        $form->setData(['profile' => ['name' => 'Ada'], 'items' => [['label' => 'One']]]);
        self::assertTrue($form->isValid());
        self::assertSame(['profile', 'items'], array_keys($form->getData()));
        self::assertSame(['name' => 'Ada'], $form->getData()['profile']);
        self::assertSame([['label' => 'One']], $form->getData()['items']);
        self::assertSame('profile', $form->getFieldset('profile')->getName());
        self::assertSame('items', $form->getCollection('items')->getName());
    }

    public function testArbitraryArrayKeysAreNeverExposedAsFieldNames(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->setFields(['wrong' => new Field('actual')]);
            }
        };
        $form->setData(['actual' => 'value']);
        self::assertSame(['actual' => 'value'], $form->getData());
    }

    public function testDuplicateFieldDefinitionsAreRejected(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
            }
        };
        $this->expectException(InvalidArgumentException::class);
        $form->setFields([new Field('name'), new Field('name')]);
    }

    public function testDuplicateFieldsetDefinitionsAreRejected(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
            }
        };
        $this->expectException(InvalidArgumentException::class);
        $form->setFieldsets([new Fieldset('profile'), new Fieldset('profile')]);
    }

    public function testDuplicateCollectionDefinitionsAreRejected(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
            }
        };
        $this->expectException(InvalidArgumentException::class);
        $form->setCollections([new Collection('items'), new Collection('items')]);
    }

    public function testClonedFieldValidatorDoesNotShareAnExistingErrorBag(): void
    {
        $original = (new Field('title'))->addValidator(new CloneFailureValidator());
        $original->setData(['title' => 'bad']);
        self::assertFalse($original->isValid());

        $copy = Field::createNew($original);
        $oldValidator = $original->getValidator(CloneFailureValidator::class);
        $newValidator = $copy->getValidator(CloneFailureValidator::class);

        self::assertCount(1, $oldValidator->getErrors());
        self::assertSame([], $newValidator->getErrors());
        self::assertNotSame($oldValidator->getErrorBag(), $newValidator->getErrorBag());
    }

    public function testClonedGroupValidatorDoesNotShareExistingErrorBag(): void
    {
        $original = new Collection('items', required: false);
        $original->addValidator(new CloneFailureValidator());
        $original->setData(['items' => []]);
        self::assertFalse($original->isValid());

        $copy = Collection::createNew($original);
        self::assertSame([], $copy->getValidator(CloneFailureValidator::class)->getErrors());
        self::assertNotSame(
            $original->getValidator(CloneFailureValidator::class)->getErrorBag(),
            $copy->getValidator(CloneFailureValidator::class)->getErrorBag(),
        );
    }

    public function testClonedFieldsetDoesNotRetainManualErrorsFromPrototype(): void
    {
        $original = new Fieldset('profile', required: false);
        $original->addField('nickname', required: false);
        $original->setData(['profile' => ['nickname' => 'Ada']]);
        $original->addGeneralError(new Error('external.invalid'));

        $copy = Fieldset::createNew($original);
        self::assertTrue($copy->isValid());
        self::assertSame([], $copy->getGeneralErrors());
        self::assertFalse($original->isValid());
    }

    public function testClonedCollectionDoesNotRetainManualErrorsFromPrototype(): void
    {
        $original = new Collection('items', required: false);
        $original->addField('value', required: false);
        $original->setData(['items' => [['value' => 'Ada']]]);
        $original->addGeneralError(new Error('external.invalid'));

        $copy = Collection::createNew($original);
        self::assertTrue($copy->isValid());
        self::assertSame([], $copy->getGeneralErrors());
        self::assertFalse($original->isValid());
    }
}

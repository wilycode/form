<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Form\Field;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;
use WilyCode\Validator\Validator;

final class FirstStopPolicyFailure extends Validator
{
    public function isValid(mixed $value): bool
    {
        $this->addError('first');
        return false;
    }
}

final class SecondStopPolicyFailure extends Validator
{
    public function isValid(mixed $value): bool
    {
        $this->addError('second');
        return false;
    }
}

final class StopOnFirstErrorTest extends TestCase
{
    private static function failingField(string $name): Field
    {
        return (new Field($name))
            ->addValidator(new FirstStopPolicyFailure())
            ->addValidator(new SecondStopPolicyFailure());
    }

    public function testDefaultStopsOnTheFirstFailureButValidatesEveryField(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                foreach (['first', 'second'] as $name) {
                    $this->addField($name)
                        ->addValidator(new FirstStopPolicyFailure())
                        ->addValidator(new SecondStopPolicyFailure());
                }
            }
        };

        $form->setData(['first' => 'bad', 'second' => 'bad']);
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getField('first')->getErrors()->getErrors());
        self::assertCount(1, $form->getField('second')->getErrors()->getErrors());
        self::assertTrue($form->getField('first')->isStopOnFirstError());
    }

    public function testFormCanCollectAllErrorsAndFieldCanOverrideItsPolicy(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->setStopOnFirstError(false);
                foreach (['all', 'one'] as $name) {
                    $this->addField($name)
                        ->addValidator(new FirstStopPolicyFailure())
                        ->addValidator(new SecondStopPolicyFailure());
                }
                $this->getField('one')->setStopOnFirstError(true);
            }
        };

        $form->setData(['all' => 'invalid', 'one' => 'invalid']);
        self::assertFalse($form->isValid());
        self::assertCount(2, $form->getField('all')->getErrors()->getErrors());
        self::assertCount(1, $form->getField('one')->getErrors()->getErrors());

        $form->setStopOnFirstError(true);
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getField('all')->getErrors()->getErrors());

        $form->setStopOnFirstError(false);
        self::assertFalse($form->isValid());
        self::assertCount(2, $form->getField('all')->getErrors()->getErrors());
        self::assertCount(1, $form->getField('one')->getErrors()->getErrors());
    }

    public function testValidatorStopFlagStillHasPriorityWhenCollectionIsEnabled(): void
    {
        $field = self::failingField('value')->setStopOnFirstError(false);
        $field->getValidator(FirstStopPolicyFailure::class)->setStop(true);
        $field->setData(['value' => 'invalid']);

        self::assertFalse($field->isValid());
        self::assertCount(1, $field->getErrors()->getErrors());
    }

    public function testNestedFieldsetsAndCollectionRowsInheritAndOverride(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->setStopOnFirstError(false);

                $profile = $this->addFieldset('profile');
                $profile->addField('first')
                    ->addValidator(new FirstStopPolicyFailure())
                    ->addValidator(new SecondStopPolicyFailure());
                $profile->setStopOnFirstError(true);

                $collection = $this->addCollection('people');
                $collection->addField('name')
                    ->addValidator(new FirstStopPolicyFailure())
                    ->addValidator(new SecondStopPolicyFailure());
            }
        };

        $form->setData([
            'profile' => ['first' => 'invalid'],
            'people' => [['name' => 'a'], ['name' => 'b']],
        ]);
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getFieldset('profile')->getList()['first']->getErrors()->getErrors());
        foreach ($form->getCollection('people')->getList() as $row) {
            self::assertCount(2, $row['name']->getErrors()->getErrors());
        }

        // Updating an already populated group must reach both prototype and live rows.
        $form->setStopOnFirstError(true);
        self::assertFalse($form->isValid());
        foreach ($form->getCollection('people')->getList() as $row) {
            self::assertCount(1, $row['name']->getErrors()->getErrors());
        }
        $form->getCollection('people')->setStopOnFirstError(false);
        self::assertFalse($form->isValid());
        foreach ($form->getCollection('people')->getList() as $row) {
            self::assertCount(2, $row['name']->getErrors()->getErrors());
        }

        $form->setData([
            'profile' => ['first' => 'invalid'],
            'people' => [['name' => 'c']],
        ]);
        self::assertFalse($form->isValid());
        self::assertCount(1, $form->getFieldset('profile')->getList()['first']->getErrors()->getErrors());
        self::assertCount(2, $form->getCollection('people')->getList()[0]['name']->getErrors()->getErrors());
    }

    public function testDirectlyAddedAndReplacedDefinitionsInheritPolicy(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->setStopOnFirstError(false);
                $this->add(self::makeField('direct'));
                $this->setFields([self::makeField('replace')]);

                $fieldset = new Fieldset('extra');
                $fieldset->add(self::makeField('nested'));
                $this->setFieldsets([$fieldset]);

                $collection = new Collection('rows');
                $collection->add(self::makeField('item'));
                $this->setCollections([$collection]);
            }

            private static function makeField(string $name): Field
            {
                return (new Field($name))
                    ->addValidator(new FirstStopPolicyFailure())
                    ->addValidator(new SecondStopPolicyFailure());
            }
        };

        // setFields() replaces the directly added field. Remaining definitions inherit.
        $form->setData([
            'replace' => 'bad',
            'extra' => ['nested' => 'bad'],
            'rows' => [['item' => 'bad']],
        ]);
        self::assertFalse($form->isValid());
        self::assertCount(2, $form->getField('replace')->getErrors()->getErrors());
        self::assertCount(2, $form->getFieldset('extra')->getList()['nested']->getErrors()->getErrors());
        self::assertCount(2, $form->getCollection('rows')->getList()[0]['item']->getErrors()->getErrors());
    }

    public function testErrorsResetOnRevalidationWithDifferentData(): void
    {
        $field = self::failingField('value');
        $field->setData(['value' => 'bad']);
        self::assertFalse($field->isValid());
        self::assertCount(1, $field->getErrors()->getErrors());

        $field->setStopOnFirstError(false);
        self::assertFalse($field->isValid());
        self::assertCount(2, $field->getErrors()->getErrors());

        $field->setData([]);
        self::assertFalse($field->isValid());
        self::assertCount(1, $field->getErrors()->getErrors());
    }
}

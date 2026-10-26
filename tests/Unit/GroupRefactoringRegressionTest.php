<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Collection;
use WilyCode\Form\Field;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;

final class GroupRefactoringRegressionTest extends TestCase
{
    public function testMixedElementsRetainDataOrderAndValidation(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addField('title');
                $this->addFieldset('profile')->addField('name');
                $this->addCollection('items')->addField('label');
            }
        };

        $form->setData([
            'title' => 'Article',
            'profile' => ['name' => 'Alice'],
            'items' => [['label' => 'First']],
        ]);

        self::assertSame([
            'title' => 'Article',
            'profile' => ['name' => 'Alice'],
            'items' => [['label' => 'First']],
        ], $form->getData());
        self::assertTrue($form->isValid());

        $form->setData(['title' => 'Article', 'profile' => ['name' => '']]);
        self::assertFalse($form->isValid());
        self::assertArrayHasKey('profile', $form->getErrors());
    }

    public function testClonedFieldsetRetainsCustomFieldClassAndSeparateData(): void
    {
        $prototype = new Fieldset('profile');
        $prototype->add(new class('name') extends Field {});
        $prototype->addDefaultFieldFilter(new TrimFilter());

        $first = Fieldset::createNew($prototype)->setData(['profile' => ['name' => ' Alice ']]);
        $second = Fieldset::createNew($prototype)->setData(['profile' => ['name' => ' Bob ']]);

        self::assertSame('Alice', $first->getField('name')->getData());
        self::assertSame('Bob', $second->getField('name')->getData());
        self::assertSame(get_class($prototype->getField('name')), get_class($first->getField('name')));
        self::assertNotSame($first->getField('name'), $second->getField('name'));
    }

    public function testClonedCollectionRowsHaveIndependentFieldsAndOrder(): void
    {
        $prototype = new Collection('items');
        $prototype->addField('name');
        $prototype->addFieldset('meta')->addField('note');

        $collection = Collection::createNew($prototype)->setData([
            'items' => [
                ['name' => 'One', 'meta' => ['note' => 'First']],
                ['name' => 'Two', 'meta' => ['note' => 'Second']],
            ],
        ]);

        self::assertSame([
            ['name' => 'One', 'meta' => ['note' => 'First']],
            ['name' => 'Two', 'meta' => ['note' => 'Second']],
        ], $collection->getData());
        self::assertTrue($collection->isValid());
        self::assertNotSame($collection->getList()[0]['name'], $collection->getList()[1]['name']);
    }
}

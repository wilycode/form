<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Filter\FilterInterface;
use WilyCode\Form\Field;
use WilyCode\Form\Form;
use WilyCode\Form\Validators\FieldCompareValidator;

class SpecializedTestField extends Field
{
    public function marker(): string
    {
        return 'specialized';
    }
}

final class StatefulTestFilter implements FilterInterface
{
    public int $calls = 0;

    public function getFilteredValue(mixed $value): mixed
    {
        $this->calls++;
        return $value;
    }
}

final class FormCloneAndFieldsetValidationTest extends TestCase
{
    public function testSpecializedFieldTypeSurvivesCollectionRowCreation(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addCollection('items')->add(new SpecializedTestField('title'));
            }
        };

        $form->setData(['items' => [['title' => 'First'], ['title' => 'Second']]]);
        $items = $form->getCollection('items')->getList();
        self::assertInstanceOf(SpecializedTestField::class, $items[0]['title']);
        self::assertInstanceOf(SpecializedTestField::class, $items[1]['title']);
        self::assertSame('specialized', $items[0]['title']->marker());
    }

    public function testComparedFieldsWithinNestedFieldsetUseTheirOwnRow(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $meta = $this->addCollection('items')->addFieldset('meta');
                $meta->addField('password');
                $meta->addField('repeat')->addValidator(
                    new FieldCompareValidator($meta, 'password', 'Password'),
                );
            }
        };

        $form->setData(['items' => [
            ['meta' => ['password' => 'first', 'repeat' => 'first']],
            ['meta' => ['password' => 'second', 'repeat' => 'second']],
        ]]);
        self::assertTrue($form->isValid());

        $form->setData(['items' => [
            ['meta' => ['password' => 'first', 'repeat' => 'first']],
            ['meta' => ['password' => 'second', 'repeat' => 'wrong']],
        ]]);
        self::assertFalse($form->isValid());
        $items = $form->getCollection('items')->getList();
        self::assertTrue($items[0]['meta']->isValid());
        self::assertFalse($items[1]['meta']->isValid());
    }

    public function testComparedFieldsDirectlyInsideCollectionUseTheirOwnRow(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $rows = $this->addCollection('items');
                $rows->addField('password');
                $rows->addField('confirm')->addValidator(
                    new FieldCompareValidator($rows, 'password', 'Password'),
                );
            }
        };

        $form->setData(['items' => [
            ['password' => 'alpha', 'confirm' => 'alpha'],
            ['password' => 'beta', 'confirm' => 'beta'],
        ]]);
        self::assertTrue($form->isValid());

        $form->setData(['items' => [
            ['password' => 'alpha', 'confirm' => 'alpha'],
            ['password' => 'beta', 'confirm' => 'wrong'],
        ]]);
        self::assertFalse($form->isValid());
        $rows = $form->getCollection('items')->getList();
        self::assertTrue($rows[0]['confirm']->isValid());
        self::assertFalse($rows[1]['confirm']->isValid());
    }

    public function testEachRowHasIndependentGroupFilterInstance(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $meta = $this->addCollection('items')->addFieldset('meta');
                $meta->addFilter(new StatefulTestFilter());
                $meta->addField('title');
            }
        };

        $form->setData(['items' => [
            ['meta' => ['title' => 'A']],
            ['meta' => ['title' => 'B']],
        ]]);

        $items = $form->getCollection('items')->getList();
        $one = $items[0]['meta']->getFilter(StatefulTestFilter::class);
        $two = $items[1]['meta']->getFilter(StatefulTestFilter::class);
        self::assertNotSame($one, $two);
        self::assertSame(1, $one->calls);
        self::assertSame(1, $two->calls);
    }

    public function testOptionalFieldsetRejectsScalarButAcceptsAbsentValue(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addFieldset('profile', required: false)->addField('name');
            }
        };

        $form->setData([]);
        self::assertTrue($form->isValid());
        $form->setData(['profile' => 'malformed']);
        self::assertFalse($form->isValid());
        self::assertSame(
            'validation.array.invalid_type',
            $form->getFieldset('profile')->getGeneralErrors()[0]->getCode(),
        );
    }
}

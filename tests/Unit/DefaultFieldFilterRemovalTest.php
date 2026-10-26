<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WilyCode\Filters\NullFilter;
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Field;
use WilyCode\Form\Form;

final class DefaultFieldFilterRemovalTest extends TestCase
{
    public function testEditFormRemovesInheritedDefaultFromExistingFutureAndNestedFields(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addDefaultFieldFilter(new TrimFilter());
                $this->addDefaultFieldFilter(new NullFilter());
                $this->addField('title');
                $this->addFieldset('profile')->addField('name');
                $this->addCollection('items')->addField('label');

                $this->removeDefaultFieldFilter(TrimFilter::class);
                $this->addField('future');
                $this->getFieldset('profile')->addField('future');
                $this->getCollection('items')->addField('future');
            }
        };

        $form->setData([
            'title' => '  Title  ',
            'future' => '  Future  ',
            'profile' => ['name' => '  Name  ', 'future' => '  Nested  '],
            'items' => [['label' => '  Label  ', 'future' => '  Row  ']],
        ]);

        self::assertSame([
            'title' => '  Title  ',
            'future' => '  Future  ',
            'profile' => ['name' => '  Name  ', 'future' => '  Nested  '],
            'items' => [['label' => '  Label  ', 'future' => '  Row  ']],
        ], $form->getData());
        self::assertArrayHasKey(NullFilter::class, $form->getDefaultFieldFilters());
        self::assertArrayNotHasKey(TrimFilter::class, $form->getDefaultFieldFilters());
    }

    public function testNestedGroupAndFieldOverridesSurviveRemovingAncestorFilter(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addDefaultFieldFilter(new TrimFilter());
                $this->addField('local')->addDefaultFilter(new TrimFilter(TrimFilter::TYPE_RIGHT));
                $this->addField('explicit')->addFilter(new TrimFilter(TrimFilter::TYPE_LEFT));

                $profile = $this->addFieldset('profile');
                $profile->addDefaultFieldFilter(new TrimFilter(TrimFilter::TYPE_LEFT));
                $profile->addField('name');

                $items = $this->addCollection('items');
                $items->addDefaultFieldFilter(new TrimFilter(TrimFilter::TYPE_RIGHT));
                $items->addField('label');

                $this->removeDefaultFieldFilter(TrimFilter::class);
            }
        };

        $form->setData([
            'local' => '  Local  ',
            'explicit' => '  Explicit  ',
            'profile' => ['name' => '  Name  '],
            'items' => [['label' => '  Label  ']],
        ]);

        self::assertSame('  Local', $form->getDataByName('local'));
        self::assertSame('Explicit  ', $form->getDataByName('explicit'));
        self::assertSame('Name  ', $form->getData()['profile']['name']);
        self::assertSame('  Label', $form->getData()['items'][0]['label']);
    }

    public function testOneFieldCanOptOutOfInheritedDefaultWithoutChangingOtherFields(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addDefaultFieldFilter(new TrimFilter());
                $this->addField('password')->removeDefaultFilter(TrimFilter::class);
                $this->addField('name');
            }
        };

        $form->setData(['password' => '  secret  ', 'name' => '  Alice  ']);
        self::assertSame('  secret  ', $form->getDataByName('password'));
        self::assertSame('Alice', $form->getDataByName('name'));
    }

    public function testRemovedFieldDefaultRemainsExcludedWhenAncestorAddsItAgain(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addDefaultFieldFilter(new TrimFilter());
                $this->addField('password')->removeDefaultFilter(TrimFilter::class);
                $this->addDefaultFieldFilter(new TrimFilter(TrimFilter::TYPE_LEFT));
                $this->addField('name');
            }
        };

        $form->setData(['password' => '  secret  ', 'name' => '  Alice  ']);
        self::assertSame('  secret  ', $form->getDataByName('password'));
        self::assertSame('Alice  ', $form->getDataByName('name'));
    }

    public function testNestedGroupCanOptOutIndependentlyAndReadoptLater(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addDefaultFieldFilter(new TrimFilter());
                $profile = $this->addFieldset('profile');
                $profile->addField('name');
                $profile->removeDefaultFieldFilter(TrimFilter::class);
                $this->addDefaultFieldFilter(new TrimFilter(TrimFilter::TYPE_LEFT));
                $profile->addField('future');
            }
        };

        $form->setData(['profile' => ['name' => '  Alice  ', 'future' => '  Bob  ']]);
        self::assertSame('  Alice  ', $form->getData()['profile']['name']);
        self::assertSame('  Bob  ', $form->getData()['profile']['future']);
        self::assertArrayNotHasKey(TrimFilter::class, $form->getFieldset('profile')->getDefaultFieldFilters());

        $form->getFieldset('profile')->addDefaultFieldFilter(new TrimFilter(TrimFilter::TYPE_RIGHT));
        $form->setData(['profile' => ['name' => '  Alice  ', 'future' => '  Bob  ']]);
        self::assertSame('  Alice', $form->getData()['profile']['name']);
        self::assertSame('  Bob', $form->getData()['profile']['future']);
    }

    public function testReplacingExplicitFilterByClassStillWorks(): void
    {
        $field = new Field('name');
        $field->addFilter(new TrimFilter());
        $field->addFilter(new TrimFilter(TrimFilter::TYPE_LEFT));
        $field->setData(['name' => '  Alice  ']);
        self::assertCount(1, $field->getFilters());
        self::assertSame('Alice  ', $field->getData());
    }

    public function testRemovingMissingDefaultFilterThrowsConsistently(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addField('title');
            }
        };

        $this->expectException(RuntimeException::class);
        $form->removeDefaultFieldFilter(TrimFilter::class);
    }
}

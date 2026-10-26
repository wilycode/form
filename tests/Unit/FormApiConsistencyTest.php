<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Field;
use WilyCode\Form\Form;

final class FormApiConsistencyTest extends TestCase
{
    public function testGroupFiltersMayBeInspectedRemovedAndReplaced(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addField('title');
            }
        };

        $filter = new TrimFilter();
        $form->addFilter($filter);
        self::assertTrue($form->hasFilter(TrimFilter::class));
        self::assertSame($filter, $form->getFilter(TrimFilter::class));
        self::assertSame($form, $form->removeFilter(TrimFilter::class));
        self::assertFalse($form->hasFilter(TrimFilter::class));
        $this->expectException(RuntimeException::class);
        $form->getFilter(TrimFilter::class);
    }

    public function testDefaultFilterAccessorsAndInheritedRemoval(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addDefaultFieldFilter(new TrimFilter());
                $this->addField('title');
            }
        };

        self::assertTrue($form->hasDefaultFieldFilter(TrimFilter::class));
        self::assertInstanceOf(TrimFilter::class, $form->getDefaultFieldFilter(TrimFilter::class));
        $field = $form->getField('title');
        self::assertTrue($field->hasDefaultFilter(TrimFilter::class));
        self::assertInstanceOf(TrimFilter::class, $field->getDefaultFilter(TrimFilter::class));
        $field->removeDefaultFilter(TrimFilter::class);
        self::assertFalse($field->hasDefaultFilter(TrimFilter::class));
        self::assertTrue($form->hasDefaultFieldFilter(TrimFilter::class));
    }

    public function testListOfExplicitFiltersOverridesDefaultOfSameClass(): void
    {
        $field = new Field('title');
        $field->addDefaultFilter(new TrimFilter());
        $field->setFilters([new TrimFilter(TrimFilter::TYPE_LEFT)]);
        $field->setData(['title' => '  text  ']);

        self::assertSame('text  ', $field->getData());
        self::assertArrayHasKey(TrimFilter::class, $field->getFilters());
    }

    public function testDefaultFilterSetterAcceptsListAndNormalizesByClass(): void
    {
        $field = new Field('title');
        $field->setDefaultFilters([new TrimFilter(TrimFilter::TYPE_LEFT)]);
        self::assertSame([TrimFilter::class], array_keys($field->getDefaultFilters()));
        $field->setData(['title' => '  text  ']);
        self::assertSame('text  ', $field->getData());
    }
}

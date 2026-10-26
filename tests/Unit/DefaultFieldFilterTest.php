<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Filter\FilterInterface;
use WilyCode\Filters\NullFilter;
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Form;

final class DefaultFieldFilterTest extends TestCase
{
    public function testDefaultFieldFilterAppliesToExistingFutureAndNestedFields(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $profile = $this->addFieldset('profile');
                $profile->addField('name');

                $this->addDefaultFieldFilter(new TrimFilter());

                $this->addField('title');

                $items = $this->addCollection('items');
                $items->addField('label');
            }
        };

        $form->setData([
            'title' => '  Article  ',
            'profile' => [
                'name' => '  Simon  ',
            ],
            'items' => [
                ['label' => '  First  '],
                ['label' => '  Second  '],
            ],
        ]);

        self::assertSame([
            'title' => 'Article',
            'profile' => [
                'name' => 'Simon',
            ],
            'items' => [
                ['label' => 'First'],
                ['label' => 'Second'],
            ],
        ], $form->getData());
    }

    public function testExplicitFieldFilterIsNotOverwrittenByDefaultFilterOfSameClass(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addField('title')->addFilter(new TrimFilter(TrimFilter::TYPE_LEFT));
                $this->addDefaultFieldFilter(new TrimFilter());
            }
        };

        $form->setData(['title' => '  Article  ']);

        self::assertSame('Article  ', $form->getDataByName('title'));
    }

    public function testFormFilterProcessesWholeInputArray(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addFilter(new class implements FilterInterface {
                    public function getFilteredValue(mixed $value): mixed
                    {
                        if (!is_array($value)) {
                            return $value;
                        }

                        $value['title'] = 'Changed by form filter';

                        return $value;
                    }
                });

                $this->addField('title');
            }
        };

        $form->setData(['title' => 'Original']);

        self::assertSame('Changed by form filter', $form->getDataByName('title'));
    }

    public function testScalarFiltersDoNotCorruptNonStringValues(): void
    {
        $trim = new TrimFilter();
        $null = new NullFilter();
        $array = ['value'];

        self::assertSame($array, $trim->getFilteredValue($array));
        self::assertSame($array, $null->getFilteredValue($array));
        self::assertSame('0', $null->getFilteredValue('0'));
        self::assertSame(0, $null->getFilteredValue(0));
        self::assertFalse($null->getFilteredValue(false));
        self::assertNull($null->getFilteredValue(''));
        self::assertNull($null->getFilteredValue(null));
    }
}

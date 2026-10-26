<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Filters\ArrayItemsFilter;
use WilyCode\Filters\TrimFilter;
use WilyCode\Filter\FilterInterface;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;

final class OptionalFieldsetMalformedMultipleTest extends TestCase
{
    public function testOptionalGroupRejectsScalarMultipleInputEvenWhenItTrimsToEmpty(): void
    {
        $group = new Fieldset('profile', required: false);
        $group->addField('tags')->multiple()->addFilter(new ArrayItemsFilter(new TrimFilter()));
        $group->setData(['profile' => ['tags' => '   ']]);

        self::assertFalse($group->isValid());
        self::assertSame(
            'validation.array.invalid_type',
            $group->getField('tags')->getErrors()->getErrors()[0]->getCode(),
        );
    }

    public function testNestedOptionalGroupDoesNotDiscardMalformedMultipleInput(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $profile = $this->addFieldset('profile', required: false);
                $contact = $profile->addFieldset('contact', required: false);
                $contact->addField('tags')->multiple()->addFilter(new ArrayItemsFilter(new TrimFilter()));
            }
        };

        $form->setData(['profile' => ['contact' => ['tags' => '   ']]]);

        self::assertFalse($form->isValid());
        self::assertSame(
            'validation.array.invalid_type',
            $form->getFieldset('profile')->getFieldset('contact')->getField('tags')
                ->getErrors()->getErrors()[0]->getCode(),
        );
    }

    public function testInvalidScalarProducedByFilterCannotBeHiddenByOptionalGroup(): void
    {
        $group = new Fieldset('profile', required: false);
        $group->addField('tags')->multiple()->addFilter(new class implements FilterInterface {
            public function getFilteredValue(mixed $value): mixed
            {
                return '';
            }
        });
        $group->setData(['profile' => ['tags' => ['a']]]);

        self::assertFalse($group->isValid());
        self::assertSame(
            'validation.array.invalid_type',
            $group->getField('tags')->getErrors()->getErrors()[0]->getCode(),
        );
    }

    public function testEmptyMultipleListDoesNotRequireOptionalGroup(): void
    {
        $group = new Fieldset('profile', required: false);
        $group->addField('tags')->multiple()->addFilter(new ArrayItemsFilter(new TrimFilter()));
        $group->setData(['profile' => ['tags' => ['   ']]]);

        self::assertTrue($group->isValid());
        self::assertSame([], $group->getData());
    }

    public function testMalformedMultipleFieldInsideCollectionCannotBeHiddenByOptionalParent(): void
    {
        $group = new Fieldset('profile', required: false);
        $rows = $group->addCollection('rows', required: false);
        $rows->addField('tags')->multiple()->addFilter(new ArrayItemsFilter(new TrimFilter()));
        $group->setData(['profile' => ['rows' => [['tags' => '   ']]]]);

        self::assertFalse($group->isValid());
    }
}

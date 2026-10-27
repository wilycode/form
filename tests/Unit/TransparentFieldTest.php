<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Field;
use WilyCode\Form\Form;
use WilyCode\Validator\Validator;

final class TransparentFieldTest extends TestCase
{
    public function test_transparent_field_is_validated_but_excluded_from_business_data(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addField('name');

                $csrf = (new Field('_csrf'))->transparent();
                $csrf->addValidator(new class extends Validator {
                    public function isValid(mixed $value): bool
                    {
                        if ($value === 'correct') {
                            return true;
                        }

                        $this->addError('invalid');
                        return false;
                    }
                });

                $this->add($csrf);
            }
        };

        $form->setData(['name' => 'Jan', '_csrf' => 'correct']);

        self::assertTrue($form->isValid());
        self::assertSame(['name' => 'Jan'], $form->getData());
        self::assertSame(['name' => 'Jan'], $form->getDataWithExcludes([]));
        self::assertSame('correct', $form->getDataByName('_csrf'));
        self::assertSame('correct', $form->getField('_csrf')->getData());

        $form->setData(['name' => 'Jan', '_csrf' => 'wrong']);

        self::assertFalse($form->isValid());
        self::assertTrue($form->getField('_csrf')->getErrors()->hasErrors());
        self::assertSame(['name' => 'Jan'], $form->getData());
    }

    public function test_nested_fieldsets_and_collection_rows_exclude_transparent_values(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $profile = $this->addFieldset('profile');
                $profile->addField('displayName');
                $profile->add((new Field('_metadata'))->transparent());

                $rows = $this->addCollection('rows');
                $rows->addField('label');
                $rows->add((new Field('_internal'))->transparent());
            }
        };

        $form->setData([
            'profile' => ['displayName' => 'Jan', '_metadata' => 'ignored'],
            'rows' => [
                ['label' => 'First', '_internal' => 'one'],
                ['label' => 'Second', '_internal' => 'two'],
            ],
        ]);

        self::assertTrue($form->isValid());
        self::assertSame([
            'profile' => ['displayName' => 'Jan'],
            'rows' => [['label' => 'First'], ['label' => 'Second']],
        ], $form->getData());
    }

    public function test_transparent_flag_can_be_cleared_and_survives_cloning(): void
    {
        $field = (new Field('hidden'))->transparent();

        self::assertTrue($field->isTransparent());
        self::assertTrue(Field::createNew($field)->isTransparent());
        self::assertFalse($field->transparent(false)->isTransparent());
    }
}

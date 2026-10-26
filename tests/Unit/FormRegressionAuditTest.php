<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Form\Field;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;
use WilyCode\Form\Validators\ArrayCustomValuesValidator;
use WilyCode\Validators\StringLengthValidator;

final class FormRegressionAuditTest extends TestCase
{
    public function testNumericSelectOptionsMatchValuesSubmittedAsStrings(): void
    {
        $field = (new Field('roles'))->multiple()->options([1, 2]);
        $field->setData(['roles' => ['1']]);

        self::assertTrue($field->isValid());
        self::assertSame(['1'], $field->getSelectedOptions());
        self::assertSame([], $field->getCustomValues());
    }

    public function testCustomValueValidatorSkipsKnownNumericOptionSubmittedAsString(): void
    {
        $field = (new Field('roles'))->multiple()->options([1, 2])->allowCustomValues()
            ->addValidator(new ArrayCustomValuesValidator(new StringLengthValidator()->setMin(5)));
        $field->setData(['roles' => ['1']]);

        self::assertTrue($field->isValid());
        self::assertSame([], $field->getCustomValues());
    }

    public function testRequiredFieldsetRejectsEmptyOptionalChildrenAndUnknownValues(): void
    {
        $fieldset = new Fieldset('profile');
        $fieldset->addField('nickname', required: false);

        $fieldset->setData(['profile' => ['nickname' => '']]);
        self::assertFalse($fieldset->isValid());
        $fieldset->setData(['profile' => ['unknown' => 'present']]);
        self::assertFalse($fieldset->isValid());
        $fieldset->setData(['profile' => ['nickname' => 'Ada']]);
        self::assertTrue($fieldset->isValid());
    }

    public function testRequiredMultipleValueFieldRejectsOnlyEmptyItemsButKeepsZero(): void
    {
        $field = (new Field('tags'))->multiple();
        foreach ([[''], [null], ['', null]] as $input) {
            $field->setData(['tags' => $input]);
            self::assertFalse($field->isValid());
            self::assertSame('validation.not_empty.required', $field->getErrors()->getErrors()[0]->getCode());
        }

        $field->setData(['tags' => ['0']]);
        self::assertTrue($field->isValid());
        $field->setData(['tags' => [0]]);
        self::assertTrue($field->isValid());
    }

    public function testOptionalFieldsetStillAllowsEmptyChildren(): void
    {
        $fieldset = new Fieldset('profile', required: false);
        $fieldset->addField('nickname', required: false);
        $fieldset->setData(['profile' => ['nickname' => '']]);
        self::assertTrue($fieldset->isValid());
    }

    public function testDifferentElementKindsCannotReuseSameName(): void
    {
        $form = new class extends Form {
            protected function init(): void { $this->addField('profile'); }
        };
        $this->expectException(InvalidArgumentException::class);
        $form->addFieldset('profile');
    }

    public function testSetCollectionsAlsoRejectsNameCollision(): void
    {
        $form = new class extends Form {
            protected function init(): void { $this->addField('profile'); }
        };
        $this->expectException(InvalidArgumentException::class);
        $form->setCollections(['profile' => new Collection('profile')]);
    }

    public function testReplacingSameKindRemainsSupported(): void
    {
        $form = new class extends Form {
            protected function init(): void { $this->addField('profile'); }
        };
        $form->addField('profile', required: false);
        self::assertFalse($form->getField('profile')->isRequired());
    }
}

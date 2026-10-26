<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;

final class OptionalFieldsetSubmissionRegressionTest extends TestCase
{
    public function testOptionalFieldsetAcceptsSubmittedBlankRequiredChild(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $profile = $this->addFieldset('profile', required: false);
                $profile->addField('name');
                $profile->addField('email');
            }
        };

        foreach ([[], ['profile' => []], ['profile' => ['name' => '']],
            ['profile' => ['name' => '', 'email' => null]]] as $data) {
            $form->setData($data);
            self::assertTrue($form->isValid());
        }
    }

    public function testOptionalFieldsetStillRequiresChildrenWhenPartiallyFilled(): void
    {
        $fieldset = new Fieldset('profile', required: false);
        $fieldset->addField('name');
        $fieldset->addField('email');

        $fieldset->setData(['profile' => ['name' => 'Ada', 'email' => '']]);
        self::assertFalse($fieldset->isValid());
        $fieldset->setData(['profile' => ['name' => 'Ada', 'email' => 'a@b.test']]);
        self::assertTrue($fieldset->isValid());
    }

    public function testNestedOptionalFieldsetDoesNotRequireEmptyChildren(): void
    {
        $fieldset = new Fieldset('outer', required: false);
        $inner = $fieldset->addFieldset('inner');
        $inner->addField('name');

        $fieldset->setData(['outer' => ['inner' => ['name' => '']]]);
        self::assertTrue($fieldset->isValid());

        $fieldset->setData(['outer' => ['inner' => ['name' => 'Ada']]]);
        self::assertTrue($fieldset->isValid());
    }

    public function testMalformedNestedGroupIsNotHiddenByOptionalBlankGroup(): void
    {
        $fieldset = new Fieldset('outer', required: false);
        $inner = $fieldset->addFieldset('inner', required: false);
        $inner->addField('name', required: false);

        $fieldset->setData(['outer' => ['inner' => '']]);
        self::assertFalse($fieldset->isValid());
        $fieldset->setData(['outer' => ['inner' => ['name' => '']]]);
        self::assertTrue($fieldset->isValid());
    }

    public function testMalformedRowAndDeepMalformedNestedGroupAreNotHidden(): void
    {
        $fieldset = new Fieldset('outer', required: false);
        $items = $fieldset->addCollection('items', required: false);
        $meta = $items->addFieldset('meta', required: false);
        $meta->addField('name', required: false);

        $fieldset->setData(['outer' => ['items' => ['']]]);
        self::assertFalse($fieldset->isValid());

        $fieldset->setData(['outer' => ['items' => [['meta' => '']]]]);
        self::assertFalse($fieldset->isValid());

        $fieldset->setData(['outer' => ['items' => [['meta' => ['name' => '']]]]]);
        self::assertTrue($fieldset->isValid());
    }

    public function testRequiredFieldsetStillRejectsBlankChild(): void
    {
        $fieldset = new Fieldset('profile', required: true);
        $fieldset->addField('name');
        $fieldset->setData(['profile' => ['name' => '']]);
        self::assertFalse($fieldset->isValid());
    }
}

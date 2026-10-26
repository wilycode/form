<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Fieldset;
use WilyCode\Form\Form;
use WilyCode\Validator\Validator;

final class OptionalFieldsetNormalizedInputTest extends TestCase
{
    private static function form(): Form
    {
        return new class extends Form {
            protected function init(): void
            {
                $profile = $this->addFieldset('profile', required: false);
                $profile->addField('name')->addFilter(new TrimFilter());
                $profile->addField('email');
            }
        };
    }

    public function testWhitespaceRemovedByFieldFilterDoesNotRequireOptionalGroup(): void
    {
        $form = self::form();
        $form->setData(['profile' => ['name' => "  \t ", 'email' => '']]);

        self::assertTrue($form->isValid());
        self::assertSame([], $form->getDataByName('profile'));
    }

    public function testUnknownSubmittedKeysCannotForceOptionalGroupValidation(): void
    {
        $form = self::form();
        $form->setData(['profile' => ['unknown' => 'hello', 'name' => '', 'email' => '']]);

        self::assertTrue($form->isValid());
        self::assertSame([], $form->getDataByName('profile'));
    }

    public function testMeaningfulChildValueStillMakesSiblingRequired(): void
    {
        $form = self::form();
        $form->setData(['profile' => ['name' => ' Ada ', 'email' => '']]);

        self::assertFalse($form->isValid());
        self::assertSame('Ada', $form->getDataByName('profile')['name']);
    }

    public function testZeroRemainsMeaningfulSubmittedData(): void
    {
        $form = self::form();
        $form->setData(['profile' => ['name' => '0', 'email' => '']]);

        self::assertFalse($form->isValid());
        self::assertSame('0', $form->getDataByName('profile')['name']);
    }

    public function testNestedMalformedTypeIsNotDiscardedAsEmpty(): void
    {
        $group = new Fieldset('profile', required: false);
        $group->addField('name', required: false)->addFilter(new TrimFilter());
        $group->addFieldset('address', required: false)->addField('city');
        $group->setData(['profile' => ['name' => '   ', 'address' => '']]);

        self::assertFalse($group->isValid());
    }

    public function testValidationStateIsResetBetweenEmptyAndFilledSubmissions(): void
    {
        $form = self::form();
        $form->setData(['profile' => ['name' => 'Ada', 'email' => '']]);
        self::assertFalse($form->isValid());

        $form->setData(['profile' => ['name' => '   ', 'email' => '']]);
        self::assertTrue($form->isValid());

        $form->setData(['profile' => ['name' => 'Ada', 'email' => 'ada@example.com']]);
        self::assertTrue($form->isValid());
    }

    public function testExistingGroupValidatorStillRunsForAbsentOptionalGroup(): void
    {
        $group = new Fieldset('profile', required: false);
        $group->addValidator(new class extends Validator {
            public function isValid(mixed $value): bool
            {
                if ($value === []) {
                    $this->addError('empty');
                    return false;
                }
                return true;
            }
        });
        $group->setData(['profile' => []]);

        // Explicit group validators keep their existing control over empty groups.
        self::assertFalse($group->isValid());
    }
}

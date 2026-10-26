<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use WilyCode\Form\Form;

final class FormSubmittedAndValidTest extends TestCase
{
    private function newForm(): Form
    {
        return new class extends Form {
            public int $validationCalls = 0;

            protected function init(): void
            {
                $this->addField('title');
            }

            public function isValid(): bool
            {
                ++$this->validationCalls;

                return parent::isValid();
            }
        };
    }

    public function testNonSubmittedFormIsNotValidated(): void
    {
        $form = $this->newForm();

        self::assertFalse($form->isSubmittedAndValid());
        self::assertSame(0, $form->validationCalls);
        self::assertSame([], $form->getErrors());

        $form->setRequest(new ServerRequest('GET', '/'));
        self::assertFalse($form->isSubmittedAndValid());
        self::assertSame(0, $form->validationCalls);
    }

    public function testSubmittedValidFormPassesAndRunsValidationOnce(): void
    {
        $form = $this->newForm()->setRequest(
            (new ServerRequest('POST', '/'))->withParsedBody(['title' => 'Hello']),
        );

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isSubmittedAndValid());
        self::assertSame(1, $form->validationCalls);
        self::assertSame('Hello', $form->getDataByName('title'));
    }

    public function testSubmittedInvalidFormReturnsFalseAndProvidesErrors(): void
    {
        $form = $this->newForm()->setRequest(
            (new ServerRequest('POST', '/'))->withParsedBody(['title' => '']),
        );

        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->isSubmittedAndValid());
        self::assertSame(1, $form->validationCalls);
        self::assertTrue($form->getField('title')->getErrors()->hasErrors());
    }

    public function testExistingValidationMethodStillWorksIndependently(): void
    {
        $form = $this->newForm();

        self::assertFalse($form->isValid());
        self::assertSame(1, $form->validationCalls);
        self::assertFalse($form->isSubmitted());
    }
}

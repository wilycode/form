<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use WilyCode\Form\Field;
use WilyCode\Form\Form;
use WilyCode\Form\FormMethod;

final class FormRequestBoundaryTest extends TestCase
{
    private function form(): Form
    {
        return new class extends Form {
            protected function init(): void
            {
                $this->addField('title');
                $this->addField('tags')->multiple();
            }
        };
    }

    public function testPostDataCannotInheritValuesFromQueryString(): void
    {
        $form = $this->form();
        $form->setRequest((new ServerRequest('POST', '/?title=stale'))
            ->withQueryParams(['title' => 'stale', 'tags' => ['old']])
            ->withParsedBody(['tags' => []]));
        self::assertTrue($form->isSubmitted());
        self::assertSame('', $form->getField('title')->getData());
        self::assertSame([], $form->getField('tags')->getData());
    }

    public function testGetFormOnlyReadsQueryParams(): void
    {
        $form = $this->form()->setMethod(FormMethod::Get);
        $form->setRequest((new ServerRequest('GET', '/'))
            ->withQueryParams(['title' => 'query'])
            ->withParsedBody(['title' => 'body']));
        self::assertSame('query', $form->getField('title')->getData());
    }

    public function testRequiredMultipleFieldRejectsEmptyArray(): void
    {
        $field = (new Field('tags'))->multiple()->setData(['tags' => []]);
        self::assertFalse($field->isValid());
        self::assertSame('validation.not_empty.required', $field->getErrors()->getErrors()[0]->getCode());
    }
}

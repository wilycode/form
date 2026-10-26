<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Validator\ErrorBag;
use WilyCode\Form\Field;
use WilyCode\Form\FieldInterface;
use WilyCode\Form\Fieldset;

final class FieldInterfaceContractTest extends TestCase
{
    public function test_field_exposes_required_and_html_metadata_through_interface(): void
    {
        $field = new Field('title')->setPath(['article', 'title']);

        $this->assertInstanceOf(FieldInterface::class, $field);
        $this->assertTrue($field->isRequired());
        $this->assertSame('article[title]', $field->getHtmlName());
        $this->assertSame('article_title', $field->getHtmlId());
        $this->assertInstanceOf(ErrorBag::class, $field->getErrors());
    }

    public function test_fieldset_and_collection_keep_the_same_field_contract(): void
    {
        $fieldset = new Fieldset('meta', required: false)->setPath(['article', 'meta']);
        $collection = new Collection('tags')->setPath(['article', 'tags']);

        $this->assertInstanceOf(FieldInterface::class, $fieldset);
        $this->assertInstanceOf(FieldInterface::class, $collection);

        $this->assertFalse($fieldset->isRequired());
        $this->assertTrue($collection->isRequired());

        $this->assertSame('article[meta]', $fieldset->getHtmlName());
        $this->assertSame('article[tags]', $collection->getHtmlName());
    }
}

<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Tests\Fixtures\Form\BookForm;
use WilyCode\Form\Tests\Fixtures\Form\ItemsForm;

final class HtmlPathTest extends TestCase
{
    public function test_top_level_and_fieldset_paths_generate_expected_html_names_and_ids(): void
    {
        $form = new BookForm();

        $title = $form->getField('title');
        $this->assertSame(['title'], $title->getPath());
        $this->assertSame('title', $title->getHtmlName());
        $this->assertSame('title', $title->getHtmlId());

        $authorFirstName = $form->getFieldset('author')->getField('firstName');
        $this->assertSame(['author', 'firstName'], $authorFirstName->getPath());
        $this->assertSame('author[firstName]', $authorFirstName->getHtmlName());
        $this->assertSame('author_firstName', $authorFirstName->getHtmlId());

        $extraName = $form->getFieldset('author')->getFieldset('extraData')->getField('name');
        $this->assertSame(['author', 'extraData', 'name'], $extraName->getPath());
        $this->assertSame('author[extraData][name]', $extraName->getHtmlName());
        $this->assertSame('author_extraData_name', $extraName->getHtmlId());
    }

    public function test_collection_rows_get_index_based_html_names_and_ids(): void
    {
        $form = new ItemsForm();
        $form->setData([
            'items' => [
                ['meta' => ['name' => 'Alpha']],
                ['meta' => ['name' => 'Beta']],
            ],
        ]);

        $rows = $form->getCollection('items')->getList();

        $first = $rows[0]['meta']->getField('name');
        $this->assertSame(['items', 0, 'meta', 'name'], $first->getPath());
        $this->assertSame('items[0][meta][name]', $first->getHtmlName());
        $this->assertSame('items_0_meta_name', $first->getHtmlId());

        $second = $rows[1]['meta']->getField('name');
        $this->assertSame(['items', 1, 'meta', 'name'], $second->getPath());
        $this->assertSame('items[1][meta][name]', $second->getHtmlName());
        $this->assertSame('items_1_meta_name', $second->getHtmlId());
    }
}

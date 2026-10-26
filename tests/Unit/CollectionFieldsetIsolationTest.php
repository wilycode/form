<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Validator\Error;
use WilyCode\Form\Tests\Fixtures\Form\ItemsForm;

final class CollectionFieldsetIsolationTest extends TestCase
{
    public function test_fieldset_instances_inside_collection_are_isolated_per_row(): void
    {
        $form = new ItemsForm();
        $data = [
            'items' => [
                ['meta' => ['name' => 'Alpha']],
                ['meta' => ['name' => 'Beta']],
            ],
        ];

        $form->setData($data);
        $this->assertSame($data, $form->getData());
    }

    public function test_nested_general_errors_are_not_duplicated(): void
    {
        $form = new ItemsForm();

        // meta fieldset is required, but provided as an empty array -> should trigger NotEmptyValidator once.
        $form->setData([
            'items' => [
                ['meta' => []],
            ],
        ]);

        $this->assertFalse($form->isValid());

        $generalErrors = $form->getGeneralErrors();
        $this->assertArrayHasKey('items', $generalErrors);
        $this->assertArrayHasKey(0, $generalErrors['items']);
        $this->assertArrayHasKey('meta', $generalErrors['items'][0]);

        $metaErrors = $generalErrors['items'][0]['meta'];
        $this->assertCount(1, $metaErrors, 'Expected meta general errors not to be duplicated.');
        $this->assertInstanceOf(Error::class, $metaErrors[0]);
        $this->assertSame('validation.not_empty.required', $metaErrors[0]->getCode());
    }
}

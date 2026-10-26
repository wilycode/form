<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;
use WilyCode\Validators\ArrayItemsValidator;
use WilyCode\Validators\ArrayLengthValidator;
use WilyCode\Validators\CallbackValidator;

final class CollectionArrayValidationTest extends TestCase
{
    public function test_collection_uses_array_length_validator(): void
    {
        $collection = new Collection('items');
        $collection->addField('name');
        $collection->addValidator(new ArrayLengthValidator()->setMin(2));
        $collection->setData(['items' => [['name' => 'one']]]);

        $this->assertFalse($collection->isValid());
        $this->assertSame(
            'validation.array_length.too_few',
            $collection->getGeneralErrors()[0]->getCode(),
        );
    }

    public function test_collection_can_validate_each_row_with_array_items_validator(): void
    {
        $collection = new Collection('items');
        $collection->addField('name', required: false);
        $collection->addValidator(
            new ArrayItemsValidator(
                new CallbackValidator(
                    static fn(mixed $row): bool => is_array($row) && ($row['name'] ?? '') !== 'invalid',
                ),
            ),
        );
        $collection->setData([
            'items' => [
                ['name' => 'valid'],
                ['name' => 'invalid'],
            ],
        ]);

        $this->assertFalse($collection->isValid());
        $generalErrors = $collection->getGeneralErrors();
        $this->assertArrayHasKey(1, $generalErrors);
        $this->assertSame('validation.callback.invalid', $generalErrors[1]['errors'][0]->getCode());
    }
}

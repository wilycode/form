<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Collection;

final class CollectionInvalidDataTest extends TestCase
{
    public function testScalarCollectionValueReportsInvalidType(): void
    {
        $collection = new Collection('items', false);
        $collection->addField('name');
        $collection->setData(['items' => 'invalid']);
        self::assertFalse($collection->isValid());
        self::assertSame('validation.array.invalid_type', $collection->getGeneralErrors()[0]->getCode());
    }

    public function testInvalidRowIsNotSilentlyAccepted(): void
    {
        $collection = new Collection('items', false);
        $collection->addField('name');
        $collection->setData(['items' => [['name' => 'ok'], 'invalid']]);
        self::assertFalse($collection->isValid());
    }
}

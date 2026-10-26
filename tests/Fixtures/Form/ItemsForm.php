<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Fixtures\Form;

use WilyCode\Form\Form;

/**
 * A minimal fixture form used to test nested structures like
 * Collection -> Fieldset -> Field.
 */
final class ItemsForm extends Form
{
    protected function init(): void
    {
        $items = $this->addCollection('items');

        $meta = $items->addFieldset('meta');
        $meta->addField('name');
    }
}

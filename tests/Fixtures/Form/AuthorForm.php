<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Fixtures\Form;

use WilyCode\Form\Collection;
use WilyCode\Filters\ArrayItemsFilter;
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Form;
use WilyCode\Form\Validators\ArrayCustomValuesValidator;
use WilyCode\Validators\ArrayValidator;
use WilyCode\Validators\StringLengthValidator;

class AuthorForm extends Form
{
    protected function init(): void
    {
        $this->addDefaultFieldFilter(new TrimFilter());

        $this->addField('firstName');
        $this->addField('lastName');

        $books = new Collection('books');
        $books->addValidator(new ArrayValidator());
        $books->addField('title')
            ->addValidator(new StringLengthValidator()->setMin(3));

        $this->add($books);

        $books->addField('description');

        $books->addField('tags')
            ->setEmptyValue([])
            ->allowCustomValues()
            ->multiple()
            ->options(['a', 'b', 'c'])
            ->addFilter(new ArrayItemsFilter(new TrimFilter()))
            ->addValidator(
                new ArrayCustomValuesValidator(
                    new StringLengthValidator()->setMax(5)->setMin(4),
                ),
            );
    }
}

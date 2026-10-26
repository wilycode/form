<?php


namespace WilyCode\Form\Tests\Fixtures\Form;

use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Form;

class BookForm extends Form
{

    protected function init(): void
    {
        $this->addField('title')
            ->addFilter(new TrimFilter());

        $this->addField('description')
            ->addFilter(new TrimFilter());

        $author = $this->addFieldset('author');
        $author
            ->addField('firstName')
            ->addFilter(new TrimFilter());

        $author->addField('lastName')
            ->addFilter(new TrimFilter());

        $extraData = $author
            ->addFieldset('extraData');
        $extraData
            ->addField('name')
            ->addFilter(new TrimFilter());
    }


}
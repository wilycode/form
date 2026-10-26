<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WilyCode\Validator\Error;
use WilyCode\Validator\ErrorBag;
use WilyCode\Form\Error\ErrorException;
use WilyCode\Form\Tests\Fixtures\Form\AuthorForm;
use WilyCode\Form\Tests\Fixtures\Form\BookForm;

final class FormDataTest extends TestCase
{
    public function test_get_values_includes_missing_fields_with_empty_value(): void
    {
        $form = new BookForm();
        $data = [
            'title' => 'Test',
            'author' => [
                'firstName' => 'Tom',
                'lastName' => 'Black',
                'extraData' => [
                    'name' => '',
                ],
            ],
        ];

        $form->setData($data);

        $expected = [
            'title' => 'Test',
            'description' => '',
            'author' => $data['author'],
        ];

        $this->assertSame($expected, $form->getData());
    }

    public function test_collection_values_and_new_options_are_computed_correctly(): void
    {
        $form = new AuthorForm();
        $data = [
            'firstName' => 'wwX',
            'lastName' => 'asX ',
            'books' => [
                [
                    'title' => 'aX',
                    'description' => 'Aaaa bbbb',
                    'tags' => ['aaaaaaa'],
                ],
                [
                    'title' => ' Teasdf',
                    'description' => 'AaX',
                    'tags' => ['aa'],
                ],
            ],
        ];

        $form->setData($data);

        $expected = $data;
        $expected['lastName'] = 'asX';
        $expected['books'][1]['title'] = 'Teasdf';

        $this->assertSame($expected, $form->getData());

        $books = $form->getCollection('books')->getList();

        $this->assertSame(['aaaaaaa'], $books[0]['tags']->getCustomValues());
        $this->assertSame([], $books[0]['tags']->getSelectedOptions());

        $this->assertSame(['aa'], $books[1]['tags']->getCustomValues());
        $this->assertSame([], $books[1]['tags']->getSelectedOptions());
    }

    public function test_is_valid_or_throw_throws_error_exception_with_field_errors(): void
    {
        $form = new BookForm();
        $data = [
            'title' => 'Test',
            'author' => [
                'firstName' => 'Tom',
                'lastName' => 'Black',
                'extraData' => [
                    'name' => 'John',
                ],
            ],
        ];

        $form->setData($data);

        try {
            $form->assertValid();
            self::fail('Expected ErrorException to be thrown.');
        } catch (ErrorException $exception) {
            $errors = $exception->getErrors();
            $this->assertArrayHasKey('description', $errors);
            $this->assertInstanceOf(ErrorBag::class, $errors['description']);
            $this->assertInstanceOf(Error::class, $errors['description']->getErrors()[0]);
        }
    }
}

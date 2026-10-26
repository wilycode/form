<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use Exception;
use PHPUnit\Framework\TestCase;
use WilyCode\Form\Tests\Fixtures\Form\BookForm;

final class DataApiTest extends TestCase
{
    public function test_form_can_return_data_by_name(): void
    {
        $form = new BookForm();
        $form->setData([
            'title' => 'Example',
            'description' => 'Body',
            'author' => [
                'firstName' => 'Jan',
                'lastName' => 'Kowalski',
                'extraData' => [
                    'name' => 'Meta',
                ],
            ],
        ]);

        $this->assertSame('Example', $form->getDataByName('title'));
        $this->assertSame('Body', $form->getDataByName('description'));
        $this->assertSame([
            'firstName' => 'Jan',
            'lastName' => 'Kowalski',
            'extraData' => ['name' => 'Meta'],
        ], $form->getDataByName('author'));
    }

    public function test_form_can_exclude_selected_keys_from_data(): void
    {
        $form = new BookForm();
        $form->setData([
            'title' => 'Example',
            'description' => 'Secret',
            'author' => [
                'firstName' => 'Jan',
                'lastName' => 'Kowalski',
                'extraData' => ['name' => 'Meta'],
            ],
        ]);

        $this->assertSame([
            'title' => 'Example',
            'author' => [
                'firstName' => 'Jan',
                'lastName' => 'Kowalski',
                'extraData' => ['name' => 'Meta'],
            ],
        ], $form->getDataWithExcludes(['description']));
    }

    public function test_get_data_by_name_throws_for_unknown_key(): void
    {
        $form = new BookForm();

        $this->expectException(Exception::class);
        $form->getDataByName('missing');
    }
}

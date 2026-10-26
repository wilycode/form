<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Fieldsets\ImageUploadFieldset;
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Form;

final class ImageUploadFieldsetTest extends TestCase
{
    public function testItPreservesReusableNestedImageData(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addDefaultFieldFilter(new TrimFilter());
                $this->add(new ImageUploadFieldset('avatar'));
            }
        };

        $form->setData([
            'avatar' => [
                'token' => ' 0123456789abcdef0123456789abcdef ',
                'existingId' => ' 12 ',
                'remove' => '0',
                'crop' => [
                    'x' => '10',
                    'y' => '20',
                    'width' => '400',
                    'height' => '400',
                    'rotate' => '0',
                    'scaleX' => '1',
                    'scaleY' => '1',
                ],
            ],
        ]);

        self::assertSame([
            'file' => null,
            'token' => '0123456789abcdef0123456789abcdef',
            'existingId' => '12',
            'remove' => false,
            'crop' => [
                'x' => '10',
                'y' => '20',
                'width' => '400',
                'height' => '400',
                'rotate' => '0',
                'scaleX' => '1',
                'scaleY' => '1',
            ],
        ], $form->getData()['avatar']);
    }

    public function testTheSameFieldsetCanBeUsedForDifferentImageRoles(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->add(new ImageUploadFieldset('featuredImage'));
                $this->add(new ImageUploadFieldset('avatar'));
            }
        };

        self::assertTrue($form->hasFieldset('featuredImage'));
        self::assertTrue($form->hasFieldset('avatar'));
        self::assertSame(
            ['file', 'token', 'existingId', 'remove'],
            array_keys($form->getFieldset('avatar')->getFields()),
        );
        self::assertSame(
            ImageUploadFieldset::CROP_FIELDS,
            array_keys($form->getFieldset('avatar')->getFieldset('crop')->getFields()),
        );
    }
}

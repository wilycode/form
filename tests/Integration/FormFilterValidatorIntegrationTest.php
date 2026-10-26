<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WilyCode\Filters\SlugFilter;
use WilyCode\Form\Form;
use WilyCode\Validators\RegexValidator;

final class FormFilterValidatorIntegrationTest extends TestCase
{
    public function testSlugFilterAndPublishedRegexValidatorWorkTogetherAcrossSubmissions(): void
    {
        $form = new class extends Form {
            protected function init(): void
            {
                $this->addField('slug')
                    ->addFilter(new SlugFilter())
                    ->addValidator(new RegexValidator('/\A[a-z0-9]+(?:-+[a-z0-9]+)*\z/'));
            }
        };

        $form->setData(['slug' => 'Café crème']);
        self::assertTrue($form->isValid());
        self::assertSame('cafe-creme', $form->getDataByName('slug'));

        $form->setData(['slug' => '---']);
        self::assertFalse($form->isValid());

        // Revalidating must not retain an error from the previous submission.
        $form->setData(['slug' => 'my--article']);
        self::assertTrue($form->isValid());
        self::assertTrue($form->getField('slug')->getErrors()->isEmpty());
    }
}

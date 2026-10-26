<?php

declare(strict_types=1);

namespace WilyCode\Form\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WilyCode\Form\Options;
use WilyCode\Form\Validators\InOptionsValidator;

final class StrictAssociativeOptionValidationTest extends TestCase
{
    public function testNumericMapKeysRespectStrictTypeMatching(): void
    {
        $options = new Options([1 => 'Active', 2 => 'Disabled']);

        self::assertTrue($options->contains(1, strict: true));
        self::assertTrue($options->contains(2, strict: true));
        self::assertFalse($options->contains('1', strict: true));
        self::assertFalse($options->contains('2', strict: true));
        self::assertTrue($options->contains('1', strict: false));
        self::assertTrue($options->contains('2', strict: false));
    }

    public function testStrictMapValidatorRejectsNumericStringsButLooseAllowsHtmlValues(): void
    {
        $strict = new InOptionsValidator([1 => 'Active', 2 => 'Disabled'], strict: true);
        $loose = new InOptionsValidator([1 => 'Active', 2 => 'Disabled']);

        self::assertFalse($strict->isValid('1'));
        self::assertTrue($strict->isValid(1));
        self::assertTrue($loose->isValid('1'));
        self::assertFalse($loose->isValid('99'));
    }

    public function testStringKeysAndListOptionsKeepTheirExistingBehavior(): void
    {
        $mapped = new Options(['draft' => 'Draft', 'published' => 'Published']);
        self::assertTrue($mapped->contains('draft', strict: true));
        self::assertFalse($mapped->contains('Draft', strict: true));

        $listed = new Options([1, 2]);
        self::assertFalse($listed->contains('1', strict: true));
        self::assertTrue($listed->contains('1', strict: false));
        self::assertTrue($listed->contains(1, strict: true));
    }
}

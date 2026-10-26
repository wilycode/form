<?php

declare(strict_types=1);

namespace WilyCode\Form\Fieldsets;

use LogicException;
use WilyCode\Form\Fieldset;
use WilyCode\Filters\TypeFilter;
use WilyCode\Filters\ValueType;
use WilyCode\Validators\StringLengthValidator;

/**
 * Reusable form value for an asynchronously uploaded image.
 *
 * The fieldset is infrastructure-agnostic. It only preserves the submitted
 * selection, temporary token, removal intent and crop coordinates.
 */
final class ImageUploadFieldset extends Fieldset
{
    public const string FILE = 'file';
    public const string TOKEN = 'token';
    public const string EXISTING_ID = 'existingId';
    public const string REMOVE = 'remove';
    public const string CROP = 'crop';

    /** @var list<string> */
    public const array CROP_FIELDS = ['x', 'y', 'width', 'height', 'rotate', 'scaleX', 'scaleY'];

    public function __construct(string $name, bool $required = false)
    {
        if (!class_exists(TypeFilter::class) || !class_exists(StringLengthValidator::class)) {
            throw new LogicException(
                'ImageUploadFieldset requires the optional wilycode/filters and wilycode/validators packages.',
            );
        }

        parent::__construct($name, $required);

        $this->addField(self::FILE, required: false)
            ->setEmptyValue(null);

        $this->addField(self::TOKEN, required: false)
            ->setEmptyValue('')
            ->addValidator(new StringLengthValidator()->setMax(255));

        $this->addField(self::EXISTING_ID, required: false)
            ->setEmptyValue('')
            ->addValidator(new StringLengthValidator()->setMax(20));

        $this->addField(self::REMOVE, required: false)
            ->setEmptyValue(false)
            ->addFilter(new TypeFilter(ValueType::Boolean));

        $crop = $this->addFieldset(self::CROP, required: false);
        foreach (self::CROP_FIELDS as $fieldName) {
            $crop->addField($fieldName, required: false)->setEmptyValue('');
        }
    }
}

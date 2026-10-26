<?php

declare(strict_types=1);

namespace WilyCode\Form\Internal;

use RuntimeException;
use WilyCode\Form\Collection;
use WilyCode\Form\Field;
use WilyCode\Form\FieldInterface;
use WilyCode\Form\Fieldset;
use WilyCode\Form\GetterInterface;

/** @internal Resolves sibling elements in one concrete collection row. */
final readonly class CollectionRowContext implements GetterInterface
{
    /** @param array<string, FieldInterface> $elements */
    public function __construct(private array $elements)
    {
    }

    public function getField(string $name): Field
    {
        $element = $this->elements[$name] ?? null;
        if (!$element instanceof Field) {
            throw new RuntimeException(sprintf('The "%s" field does not exist in this collection row.', $name));
        }
        return $element;
    }

    public function getFieldset(string $name): Fieldset
    {
        $element = $this->elements[$name] ?? null;
        if (!$element instanceof Fieldset) {
            throw new RuntimeException(sprintf('The "%s" fieldset does not exist in this collection row.', $name));
        }
        return $element;
    }

    public function getCollection(string $name): Collection
    {
        $element = $this->elements[$name] ?? null;
        if (!$element instanceof Collection) {
            throw new RuntimeException(sprintf('The "%s" collection does not exist in this collection row.', $name));
        }
        return $element;
    }
}

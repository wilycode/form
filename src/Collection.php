<?php

declare(strict_types=1);

namespace WilyCode\Form;

use WilyCode\Form\Internal\CollectionRowContext;
use WilyCode\Form\Validators\FieldCompareValidator;

class Collection extends AbstractFormGroup implements FieldInterface
{
    private bool $required;
    private bool $invalidData = false;

    /**
     * @var array<int|string, array<string, FieldInterface>>
     */
    private array $list = [];

    public function __construct(string $name, bool $required = true)
    {
        $this->setName($name);
        $this->required = $required;
    }

    public function setData(mixed $data): self
    {
        if (!is_array($data)) {
            $data = [];
        }

        $this->clearAddedGeneralErrors();
        $this->resetValidationErrors();
        $this->list = [];
        $this->invalidData = false;

        $list = array_key_exists($this->getName(), $data) ? $data[$this->getName()] : [];

        foreach ($this->getFilters() as $filter) {
            $list = $filter->getFilteredValue($list);
        }

        if (!is_array($list)) {
            $this->invalidData = true;
            return $this;
        }

        foreach ($list as $index => $values) {
            if (!is_array($values)) {
                $this->invalidData = true;
                continue;
            }
            // Preserve the established field/collection/fieldset order per row.
            foreach ([$this->fields, $this->collections, $this->fieldsets] as $definitions) {
                foreach ($definitions as $name => $definition) {
                    $element = self::cloneElement($definition)
                        ->setPath([...$this->getPath(), $index, $name]);
                    $this->list[$index][$name] = $element->setData($values);
                }
            }

            // A comparison on a collection field refers to this row's siblings,
            // not the collection's unpopulated prototype fields.
            $row = $this->list[$index] ?? [];
            $rowContext = new CollectionRowContext($row);
            foreach ($row as $element) {
                self::rebindRowComparisons($element, $this, $rowContext);
            }
        }

        return $this;
    }

    private static function rebindRowComparisons(
        FieldInterface $element,
        GetterInterface $source,
        GetterInterface $rowContext,
    ): void {
        foreach ($element->getValidators() as $validator) {
            if ($validator instanceof FieldCompareValidator) {
                $validator->rebindContext($source, $rowContext);
            }
        }

        if ($element instanceof Fieldset || $element instanceof self) {
            foreach ($element->getFields() as $field) {
                self::rebindRowComparisons($field, $source, $rowContext);
            }
            foreach ($element->getFieldsets() as $fieldset) {
                self::rebindRowComparisons($fieldset, $source, $rowContext);
            }
            foreach ($element->getCollections() as $collection) {
                self::rebindRowComparisons($collection, $source, $rowContext);
            }
        }
    }

    public function isValid(): bool
    {
        $this->resetValidationErrors();

        $isValid = !$this->hasAddedGeneralErrors();

        if ($this->invalidData) {
            $this->generalErrors[] = new \WilyCode\Validator\Error('validation.array.invalid_type');
            $isValid = false;
        }

        if (!$this->invalidData && $this->isRequired() && !self::elementHasNonEmptyData($this)) {
            $this->generalErrors[] = new \WilyCode\Validator\Error('validation.not_empty.required');
            $isValid = false;
        }

        if (!$this->isGeneralValid()) {
            $isValid = false;
        }

        // Row-level general errors can share numeric keys with general
        // validator errors. Build the row tree first, then append root errors.
        $groupErrors = $this->generalErrors;
        $this->generalErrors = [];

        foreach ($this->list as $index => $data) {
            foreach ($data as $name => $field) {
                if (!$field->isValid()) {
                    $isValid = false;
                }

                if ($field instanceof self || $field instanceof Fieldset) {
                    $this->generalErrors[$index][$name] = self::mergeNestedErrors(
                        is_array($this->generalErrors[$index][$name] ?? null) ? $this->generalErrors[$index][$name] : [],
                        $field->getGeneralErrors(),
                    );
                }

                $this->errors[$index][$name] = $field->getErrors();
            }
        }

        foreach ($groupErrors as $index => $error) {
            if ($error instanceof \WilyCode\Validator\Error) {
                $this->generalErrors[] = $error;
            } elseif (is_array($error)) {
                // A group validator and a nested row can each report errors
                // for the same numeric row index.
                $this->generalErrors[$index] = self::mergeNestedErrors(
                    is_array($this->generalErrors[$index] ?? null) ? $this->generalErrors[$index] : [],
                    $error,
                );
            }
        }

        return $isValid;
    }

    /** @return array<int|string, array<string, mixed>> */
    public function getData(): array
    {
        $values = [];
        foreach ($this->list as $index => $data) {
            foreach ($data as $name => $field) {
                if ($field instanceof Field && $field->isTransparent()) {
                    continue;
                }
                $values[$index][$name] = $field->getData();
            }
        }

        return $values;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return array<int|string, array<string, FieldInterface>>
     */
    public function getList(): array
    {
        return $this->list;
    }

    public static function createNew(Collection $collection): Collection
    {
        $newCollection = clone $collection;
        $newCollection->list = [];
        $newCollection->invalidData = false;
        $newCollection->copyGroupRulesFrom($collection);

        $newCollection->copyDefinitionsFrom($collection);

        $newCollection->rebindClonedValidatorContexts($collection);
        return $newCollection;
    }
}

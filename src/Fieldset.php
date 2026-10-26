<?php

declare(strict_types=1);

namespace WilyCode\Form;

class Fieldset extends AbstractFormGroup implements FieldInterface
{
    private bool $required;
    private bool $invalidData = false;

    /**
     * @var array<string, FieldInterface>
     */
    private array $list = [];

    public function __construct(string $name, bool $required = true)
    {
        $this->setName($name);
        $this->required = $required;
    }

    public function isRequired(): bool
    {
        return $this->required;
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

        if (!array_key_exists($this->getName(), $data)) {
            return $this;
        }

        $values = $data[$this->getName()];
        if (!is_array($values)) {
            $this->invalidData = true;
            return $this;
        }

        // Apply group filters even to an explicitly submitted empty array.
        // A filter may normalize or populate that array; unchanged empties
        // still retain the original absent-group behavior.
        foreach ($this->getFilters() as $filter) {
            $values = $filter->getFilteredValue($values);
        }

        if (!is_array($values)) {
            $this->invalidData = true;
            return $this;
        }

        if ($values === []) {
            return $this;
        }

        // Preserve the established field/collection/fieldset order.
        foreach ([$this->fields, $this->collections, $this->fieldsets] as $definitions) {
            foreach ($definitions as $name => $definition) {
                $this->list[$name] = self::cloneElement($definition)->setData($values);
            }
        }

        // Determine whether an optional group is empty only after child
        // filters have processed its actual, declared fields. Raw whitespace
        // and unknown request keys must not force required children to run.
        // Do not hide malformed nested groups when their normalized data is empty.
        if (!$this->required && !$this->hasNonEmptyData()
            && !self::hasMalformedNestedGroupData($this, $values)
            && !self::hasMalformedNormalizedMultipleData($this)) {
            $this->list = [];
        }

        return $this;
    }

    /** An empty optional group must not hide malformed nested fieldsets or rows. */
    private static function hasMalformedNestedGroupData(AbstractFormGroup $group, array $values): bool
    {
        // A multiple field can become empty after item filters (e.g. trimming
        // a scalar whitespace value). Its original scalar input is still
        // malformed and must not be hidden by collapsing an optional group.
        foreach ($group->getFields() as $name => $field) {
            if (!$field->isMultiple() || !array_key_exists($name, $values)) {
                continue;
            }

            $submitted = $values[$name];
            if (!is_array($submitted) && $submitted !== null
                && $submitted !== $field->getEmptyValue()) {
                return true;
            }
        }

        foreach ($group->getFieldsets() as $name => $fieldset) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            if (!is_array($values[$name])
                || self::hasMalformedNestedGroupData($fieldset, $values[$name])) {
                return true;
            }
        }

        foreach ($group->getCollections() as $name => $collection) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            if (!is_array($values[$name])) {
                return true;
            }
            foreach ($values[$name] as $row) {
                if (!is_array($row)
                    || self::hasMalformedNestedGroupData($collection, $row)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** A field filter that changes an array into a scalar must not hide invalid multiple data. */
    private static function hasMalformedNormalizedMultipleData(FieldInterface $element): bool
    {
        if ($element instanceof Field) {
            return $element->isMultiple() && !is_array($element->getData());
        }

        if ($element instanceof self) {
            foreach ($element->getList() as $child) {
                if (self::hasMalformedNormalizedMultipleData($child)) {
                    return true;
                }
            }
        } elseif ($element instanceof Collection) {
            foreach ($element->getList() as $row) {
                foreach ($row as $child) {
                    if (self::hasMalformedNormalizedMultipleData($child)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        $values = [];

        foreach ($this->list as $field) {
            $values[$field->getName()] = $field->getData();
        }

        return $values;
    }

    /**
     * @return array<string, FieldInterface>
     */
    public function getList(): array
    {
        return $this->list;
    }

    public function getField(string $name): Field
    {
        if (($this->list[$name] ?? null) instanceof Field) {
            return $this->list[$name];
        }

        return parent::getField($name);
    }

    public function getFieldset(string $name): Fieldset
    {
        if (($this->list[$name] ?? null) instanceof self) {
            return $this->list[$name];
        }

        return parent::getFieldset($name);
    }

    public function getCollection(string $name): Collection
    {
        if (($this->list[$name] ?? null) instanceof Collection) {
            return $this->list[$name];
        }

        return parent::getCollection($name);
    }

    public function isValid(): bool
    {
        $this->resetValidationErrors();

        $isValid = !$this->hasAddedGeneralErrors();

        if ($this->invalidData) {
            $this->generalErrors[] = new \WilyCode\Validator\Error('validation.array.invalid_type');
            $isValid = false;
        }

        if (!$this->invalidData && $this->isRequired() && !$this->hasNonEmptyData()) {
            $this->generalErrors[] = new \WilyCode\Validator\Error('validation.not_empty.required');
            $isValid = false;
        }

        if (!$this->isGeneralValid()) {
            $isValid = false;
        }

        foreach ($this->list as $field) {
            if (!$field->isValid()) {
                $isValid = false;
            }

            if ($field instanceof Collection || $field instanceof self) {
                $name = $field->getName();
                $this->generalErrors[$name] = self::mergeNestedErrors(
                    is_array($this->generalErrors[$name] ?? null) ? $this->generalErrors[$name] : [],
                    $field->getGeneralErrors(),
                );
            }

            $this->errors[$field->getName()] = $field->getErrors();
        }

        return $isValid;
    }

    /** A required fieldset must contain at least one meaningful submitted value. */
    private function hasNonEmptyData(): bool
    {
        return self::elementHasNonEmptyData($this);
    }

    public static function createNew(Fieldset $fieldset): Fieldset
    {
        $newFieldset = clone $fieldset;
        $newFieldset->list = [];
        $newFieldset->invalidData = false;
        $newFieldset->copyGroupRulesFrom($fieldset);

        $newFieldset->copyDefinitionsFrom($fieldset);

        $newFieldset->rebindClonedValidatorContexts($fieldset);
        return $newFieldset;
    }
}

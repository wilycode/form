<?php

declare(strict_types=1);

namespace WilyCode\Form;

use InvalidArgumentException;
use RuntimeException;
use WilyCode\Filter\FilterInterface;
use WilyCode\Validator\Error;
use WilyCode\Validator\ErrorBag;
use WilyCode\Validator\ValidatorInterface;
use WilyCode\Form\Validators\FieldCompareValidator;

abstract class AbstractFormGroup implements GetterInterface
{
    use HtmlPathTrait;

    private string $name = '';
    /** A local override takes precedence over any inherited configuration. */
    private ?bool $stopOnFirstError = null;
    private bool $inheritedStopOnFirstError = true;

    /** @var array<class-string<FilterInterface>, FilterInterface> */
    private array $generalFilters = [];

    /** @var array<class-string<FilterInterface>, FilterInterface> */
    private array $defaultFieldFilters = [];

    /** @var array<class-string<FilterInterface>, true> */
    private array $localDefaultFilterClasses = [];

    /** @var array<class-string<FilterInterface>, true> */
    private array $excludedDefaultFilterClasses = [];

    /** @var array<class-string<ValidatorInterface>, ValidatorInterface> */
    private array $generalValidators = [];

    /** @var array<int|string, Error|array<int|string, mixed>> */
    protected array $generalErrors = [];

    /** @var list<Error> Errors manually attached to the current form data. */
    private array $addedGeneralErrors = [];

    /** @var array<string, Field> */
    protected array $fields = [];

    /** @var array<string, Fieldset> */
    protected array $fieldsets = [];

    /** @var array<string, Collection> */
    protected array $collections = [];

    /** @var array<int|string, mixed> */
    protected array $errors = [];

    /**
     * Configure whether each field stops at its first failing validator.
     * Applies to existing and future fields, fieldsets and collection rows.
     */
    public function setStopOnFirstError(bool $stop): static
    {
        $this->stopOnFirstError = $stop;
        $this->propagateStopOnFirstError();
        return $this;
    }

    public function isStopOnFirstError(): bool
    {
        return $this->stopOnFirstError ?? $this->inheritedStopOnFirstError;
    }

    /** @internal Apply an ancestor's policy without overriding this group's local choice. */
    public function inheritStopOnFirstError(bool $stop): void
    {
        $this->inheritedStopOnFirstError = $stop;
        $this->propagateStopOnFirstError();
    }

    private function propagateStopOnFirstError(): void
    {
        $stop = $this->isStopOnFirstError();

        foreach ($this->getDefinedElements() as $element) {
            $element->inheritStopOnFirstError($stop);
        }

        // Apply configuration also when a caller updates an already populated group.
        if ($this instanceof Fieldset) {
            foreach ($this->getList() as $element) {
                $element->inheritStopOnFirstError($stop);
            }
        } elseif ($this instanceof Collection) {
            foreach ($this->getList() as $row) {
                foreach ($row as $element) {
                    $element->inheritStopOnFirstError($stop);
                }
            }
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected function setName(string $name): void
    {
        $this->name = $name;
        $this->syncChildPaths();
    }

    /**
     * @param list<int|string> $path
     */
    public function setPath(array $path): static
    {
        $this->syncOwnPath($path);

        return $this;
    }

    /** @param array<array-key, FilterInterface> $filters */
    public function setFilters(array $filters): self
    {
        $this->generalFilters = [];
        return $this->addFilters($filters);
    }

    /** @return FilterInterface[] */
    public function getFilters(): array
    {
        return $this->generalFilters;
    }

    public function hasFilter(string $className): bool
    {
        return isset($this->generalFilters[$className]);
    }

    public function getFilter(string $className): FilterInterface
    {
        if (!$this->hasFilter($className)) {
            throw new RuntimeException(sprintf('The group does not contain "%s" filter.', $className));
        }

        return $this->generalFilters[$className];
    }

    public function removeFilter(string $className): self
    {
        if (!$this->hasFilter($className)) {
            throw new RuntimeException(sprintf('The group does not contain "%s" filter.', $className));
        }

        unset($this->generalFilters[$className]);
        return $this;
    }

    public function addFilter(FilterInterface $filter): self
    {
        $this->generalFilters[get_class($filter)] = $filter;
        return $this;
    }

    /** @param array<array-key, FilterInterface> $filters */
    public function addFilters(array $filters): self
    {
        foreach ($filters as $filter) {
            $this->addFilter($filter);
        }

        return $this;
    }

    /** @return array<class-string<FilterInterface>, FilterInterface> */
    public function getDefaultFieldFilters(): array
    {
        return $this->defaultFieldFilters;
    }

    public function hasDefaultFieldFilter(string $className): bool
    {
        return isset($this->defaultFieldFilters[$className]);
    }

    public function getDefaultFieldFilter(string $className): FilterInterface
    {
        if (!$this->hasDefaultFieldFilter($className)) {
            throw new RuntimeException(sprintf('The group does not contain "%s" default field filter.', $className));
        }

        return $this->defaultFieldFilters[$className];
    }

    public function addDefaultFieldFilter(FilterInterface $filter): self
    {
        $className = $filter::class;
        $this->localDefaultFilterClasses[$className] = true;
        unset($this->excludedDefaultFilterClasses[$className]);
        $this->defaultFieldFilters[$className] = $filter;
        $this->propagateDefaultFieldFilter($filter);

        return $this;
    }

    /**
     * Remove a default filter from this subtree, including existing nested elements.
     * Locally added defaults in descendants, and explicit field filters, remain intact.
     */
    public function removeDefaultFieldFilter(string $className): self
    {
        if (!isset($this->defaultFieldFilters[$className])) {
            throw new RuntimeException(sprintf('The group does not contain "%s" default field filter.', $className));
        }

        unset($this->defaultFieldFilters[$className], $this->localDefaultFilterClasses[$className]);
        $this->excludedDefaultFilterClasses[$className] = true;
        $this->propagateRemovedDefaultFieldFilter($className);

        return $this;
    }

    /** @internal Pass down an ancestor's filter without replacing local group overrides. */
    protected function inheritDefaultFieldFilter(FilterInterface $filter): void
    {
        $className = $filter::class;
        if (isset($this->excludedDefaultFilterClasses[$className])) {
            return;
        }

        if (!isset($this->localDefaultFilterClasses[$className])) {
            $this->defaultFieldFilters[$className] = $filter;
        }

        $this->propagateDefaultFieldFilter($this->defaultFieldFilters[$className]);
    }

    /** @internal Remove an inherited filter without removing a descendant override. */
    protected function removeInheritedDefaultFieldFilter(string $className): void
    {
        if (isset($this->localDefaultFilterClasses[$className])) {
            return;
        }

        unset($this->defaultFieldFilters[$className]);
        $this->propagateRemovedDefaultFieldFilter($className);
    }

    private function propagateDefaultFieldFilter(FilterInterface $filter): void
    {
        foreach ($this->fields as $field) {
            $this->applyDefaultFieldFilter($field, $filter);
        }

        foreach ($this->fieldsets as $fieldset) {
            $fieldset->inheritDefaultFieldFilter(clone $filter);
        }

        foreach ($this->collections as $collection) {
            $collection->inheritDefaultFieldFilter(clone $filter);
        }
    }

    private function propagateRemovedDefaultFieldFilter(string $className): void
    {
        foreach ($this->fields as $field) {
            $field->removeInheritedDefaultFilter($className);
        }

        foreach ($this->fieldsets as $fieldset) {
            $fieldset->removeInheritedDefaultFieldFilter($className);
        }

        foreach ($this->collections as $collection) {
            $collection->removeInheritedDefaultFieldFilter($className);
        }
    }

    /** @param array<class-string<FilterInterface>, FilterInterface> $filters */
    protected function inheritDefaultFieldFilters(array $filters): void
    {
        foreach ($filters as $filter) {
            $this->inheritDefaultFieldFilter($filter);
        }
    }

    /** @internal Preserve local versus inherited filters when cloning a fieldset or collection. */
    protected function copyDefaultFieldFilterConfigurationFrom(self $source): void
    {
        $this->defaultFieldFilters = [];
        foreach ($source->defaultFieldFilters as $className => $filter) {
            $this->defaultFieldFilters[$className] = clone $filter;
        }

        $this->localDefaultFilterClasses = $source->localDefaultFilterClasses;
        $this->excludedDefaultFilterClasses = $source->excludedDefaultFilterClasses;
    }

    /** Copy rules without sharing mutable filters or validators between collection rows. */
    protected function copyGroupRulesFrom(self $source): void
    {
        $this->generalFilters = [];
        foreach ($source->generalFilters as $className => $filter) {
            $this->generalFilters[$className] = clone $filter;
        }

        $this->generalValidators = [];
        foreach ($source->generalValidators as $className => $validator) {
            $this->generalValidators[$className] = (clone $validator)->clearErrors();
        }

        $this->copyDefaultFieldFilterConfigurationFrom($source);
        $this->stopOnFirstError = $source->stopOnFirstError;
        $this->inheritedStopOnFirstError = $source->inheritedStopOnFirstError;
        $this->addedGeneralErrors = [];
        $this->generalErrors = [];
        $this->errors = [];
    }

    /** Clone a definition while preserving custom field and group subclasses. */
    protected static function cloneElement(Field|Fieldset|Collection $element): Field|Fieldset|Collection
    {
        return match (true) {
            $element instanceof Field => Field::createNew($element),
            $element instanceof Fieldset => Fieldset::createNew($element),
            $element instanceof Collection => Collection::createNew($element),
        };
    }

    /** Copy the three kinds of definitions into a new group instance. */
    protected function copyDefinitionsFrom(self $source): void
    {
        $fields = [];
        foreach ($source->fields as $name => $field) {
            $fields[$name] = self::cloneElement($field);
        }

        $fieldsets = [];
        foreach ($source->fieldsets as $name => $fieldset) {
            $fieldsets[$name] = self::cloneElement($fieldset);
        }

        $collections = [];
        foreach ($source->collections as $name => $collection) {
            $collections[$name] = self::cloneElement($collection);
        }

        $this->setFields($fields)->setFieldsets($fieldsets)->setCollections($collections);
    }

    /** Rebind validators whose context points to a copied group in the same subtree. */
    protected function rebindClonedValidatorContexts(self $source): void
    {
        $bindings = [];
        self::collectGroupBindings($source, $this, $bindings);
        self::rebindValidatorsInGroup($this, $bindings);
    }

    /** @param array<int, array{0: self, 1: self}> $bindings */
    private static function collectGroupBindings(self $source, self $copy, array &$bindings): void
    {
        $bindings[] = [$source, $copy];

        foreach ($source->fieldsets as $name => $child) {
            if (isset($copy->fieldsets[$name])) {
                self::collectGroupBindings($child, $copy->fieldsets[$name], $bindings);
            }
        }

        foreach ($source->collections as $name => $child) {
            if (isset($copy->collections[$name])) {
                self::collectGroupBindings($child, $copy->collections[$name], $bindings);
            }
        }
    }

    /** @param array<int, array{0: self, 1: self}> $bindings */
    private static function rebindValidatorsInGroup(self $group, array $bindings): void
    {
        foreach ($group->generalValidators as $validator) {
            self::rebindCompareContext($validator, $bindings);
        }

        foreach ($group->fields as $field) {
            foreach ($field->getValidators() as $validator) {
                self::rebindCompareContext($validator, $bindings);
            }
        }

        foreach ($group->fieldsets as $child) {
            self::rebindValidatorsInGroup($child, $bindings);
        }

        foreach ($group->collections as $child) {
            self::rebindValidatorsInGroup($child, $bindings);
        }
    }

    /** @param array<int, array{0: self, 1: self}> $bindings */
    private static function rebindCompareContext(ValidatorInterface $validator, array $bindings): void
    {
        if (!$validator instanceof FieldCompareValidator) {
            return;
        }

        foreach ($bindings as [$source, $copy]) {
            $validator->rebindContext($source, $copy);
        }
    }

    /** @param array<class-string<FilterInterface>, FilterInterface> $filters */
    public function addDefaultFieldFilters(array $filters): self
    {
        foreach ($filters as $filter) {
            $this->addDefaultFieldFilter($filter);
        }

        return $this;
    }

    /** Prevent silent data and error loss when fields and nested groups share a name. */
    private function assertElementNameAvailable(string $name, string $kind): void
    {
        $collision = ($kind !== 'field' && isset($this->fields[$name]))
            || ($kind !== 'fieldset' && isset($this->fieldsets[$name]))
            || ($kind !== 'collection' && isset($this->collections[$name]));

        if ($collision) {
            throw new InvalidArgumentException(sprintf(
                'An element named "%s" already exists with another type.',
                $name,
            ));
        }
    }

    public function add(Field|Fieldset|Collection $element): void
    {
        if ($element instanceof Field) {
            $this->assertElementNameAvailable($element->getName(), 'field');
            $this->applyDefaultFieldFilters($element);
            $element->inheritStopOnFirstError($this->isStopOnFirstError());
            $this->fields[$element->getName()] = $element;
        }

        if ($element instanceof Fieldset) {
            $this->assertElementNameAvailable($element->getName(), 'fieldset');
            $element->inheritDefaultFieldFilters($this->cloneDefaultFieldFilters());
            $element->inheritStopOnFirstError($this->isStopOnFirstError());
            $this->fieldsets[$element->getName()] = $element;
        }

        if ($element instanceof Collection) {
            $this->assertElementNameAvailable($element->getName(), 'collection');
            $element->inheritDefaultFieldFilters($this->cloneDefaultFieldFilters());
            $element->inheritStopOnFirstError($this->isStopOnFirstError());
            $this->collections[$element->getName()] = $element;
        }

        $this->syncChildPath($element);
    }

    public function addField(string $name, bool $required = true): Field
    {
        $this->assertElementNameAvailable($name, 'field');
        $field = new Field($name, $required);
        $this->applyDefaultFieldFilters($field);
        $field->inheritStopOnFirstError($this->isStopOnFirstError());
        $this->fields[$field->getName()] = $field;
        $this->syncChildPath($field);

        return $field;
    }

    public function removeField(string $name): self
    {
        $this->hasField($name, true);
        unset($this->fields[$name]);
        return $this;
    }

    /**
     * @template T of Field|Fieldset|Collection
     * @param array<int|string, T> $elements
     * @param 'field'|'fieldset'|'collection' $kind
     * @return array<string, T>
     */
    private function indexElementsByName(array $elements, string $kind): array
    {
        $indexed = [];
        foreach ($elements as $element) {
            $name = $element->getName();
            $this->assertElementNameAvailable($name, $kind);
            if (array_key_exists($name, $indexed)) {
                throw new InvalidArgumentException(sprintf('Duplicate %s name "%s".', $kind, $name));
            }
            $indexed[$name] = $element;
        }

        return $indexed;
    }

    /** @param array<int|string, Field> $fields */
    public function setFields(array $fields): self
    {
        $indexed = $this->indexElementsByName($fields, 'field');
        foreach ($indexed as $field) {
            $this->applyDefaultFieldFilters($field);
            $field->inheritStopOnFirstError($this->isStopOnFirstError());
        }

        $this->fields = $indexed;
        $this->syncChildPaths();
        return $this;
    }

    /** @return array<string, Field> */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function hasField(string $name, bool $withException = false): bool
    {
        $exists = array_key_exists($name, $this->fields);
        if (!$exists && $withException) {
            throw new RuntimeException(sprintf('The "%s" field does not exist.', $name));
        }
        return $exists;
    }

    public function getField(string $name): Field
    {
        $this->hasField($name, true);

        return $this->fields[$name];
    }

    public function addFieldset(string $name, bool $required = true): Fieldset
    {
        $this->assertElementNameAvailable($name, 'fieldset');
        $fieldset = new Fieldset($name, $required);
        $fieldset->inheritDefaultFieldFilters($this->cloneDefaultFieldFilters());
        $fieldset->inheritStopOnFirstError($this->isStopOnFirstError());
        $this->fieldsets[$fieldset->getName()] = $fieldset;
        $this->syncChildPath($fieldset);

        return $fieldset;
    }

    public function removeFieldset(string $name): self
    {
        $this->hasFieldset($name, true);
        unset($this->fieldsets[$name]);
        return $this;
    }

    /** @param array<int|string, Fieldset> $fieldsets */
    public function setFieldsets(array $fieldsets): self
    {
        $indexed = $this->indexElementsByName($fieldsets, 'fieldset');
        foreach ($indexed as $fieldset) {
            $fieldset->inheritDefaultFieldFilters($this->cloneDefaultFieldFilters());
            $fieldset->inheritStopOnFirstError($this->isStopOnFirstError());
        }

        $this->fieldsets = $indexed;
        $this->syncChildPaths();
        return $this;
    }

    /** @return array<string, Fieldset> */
    public function getFieldsets(): array
    {
        return $this->fieldsets;
    }

    public function hasFieldset(string $name, bool $withException = false): bool
    {
        $exists = array_key_exists($name, $this->fieldsets);
        if (!$exists && $withException) {
            throw new RuntimeException(sprintf('The "%s" fieldset does not exist.', $name));
        }
        return $exists;
    }

    public function getFieldset(string $name): Fieldset
    {
        $this->hasFieldset($name, true);
        return $this->fieldsets[$name];
    }

    /** @return array<string, Collection> */
    public function getCollections(): array
    {
        return $this->collections;
    }

    /** @param array<int|string, Collection> $collections */
    public function setCollections(array $collections): self
    {
        $indexed = $this->indexElementsByName($collections, 'collection');
        foreach ($indexed as $collection) {
            $collection->inheritDefaultFieldFilters($this->cloneDefaultFieldFilters());
            $collection->inheritStopOnFirstError($this->isStopOnFirstError());
        }

        $this->collections = $indexed;
        $this->syncChildPaths();
        return $this;
    }

    public function addCollection(string $name, bool $required = true): Collection
    {
        $this->assertElementNameAvailable($name, 'collection');
        $collection = new Collection($name, $required);
        $collection->inheritDefaultFieldFilters($this->cloneDefaultFieldFilters());
        $collection->inheritStopOnFirstError($this->isStopOnFirstError());
        $this->collections[$collection->getName()] = $collection;
        $this->syncChildPath($collection);

        return $collection;
    }

    public function removeCollection(string $name): self
    {
        $this->hasCollection($name, true);
        unset($this->collections[$name]);
        return $this;
    }

    public function hasCollection(string $name, bool $withException = false): bool
    {
        $exists = array_key_exists($name, $this->collections);
        if (!$exists && $withException) {
            throw new RuntimeException(sprintf('The "%s" collection does not exist.', $name));
        }
        return $exists;
    }

    public function getCollection(string $name): Collection
    {
        $this->hasCollection($name, true);

        return $this->collections[$name];
    }

    /** @return array<int|string, mixed> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addGeneralError(Error $error): self
    {
        $this->addedGeneralErrors[] = $error;
        $this->generalErrors[] = $error;
        return $this;
    }

    /** A new data payload invalidates manually added errors from previous data. */
    protected function clearAddedGeneralErrors(): void
    {
        $this->addedGeneralErrors = [];
    }

    /** Rebuild validation results while keeping manual errors for unchanged data. */
    protected function resetValidationErrors(): void
    {
        $this->errors = [];
        $this->generalErrors = $this->addedGeneralErrors;
        foreach ($this->generalValidators as $validator) {
            $validator->clearErrors();
        }
    }

    protected function hasAddedGeneralErrors(): bool
    {
        return $this->addedGeneralErrors !== [];
    }

    /** Inspect actual field values, not merely the presence of collection rows. */
    protected static function elementHasNonEmptyData(FieldInterface $element): bool
    {
        if ($element instanceof Field) {
            return !$element->isEmpty();
        }

        if ($element instanceof Fieldset) {
            foreach ($element->getList() as $child) {
                if (self::elementHasNonEmptyData($child)) {
                    return true;
                }
            }
        } elseif ($element instanceof Collection) {
            foreach ($element->getList() as $row) {
                foreach ($row as $child) {
                    if (self::elementHasNonEmptyData($child)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @return array<int|string, Error|array<int|string, mixed>> */
    public function getGeneralErrors(): array
    {
        return $this->generalErrors;
    }

    /** @return array<class-string<ValidatorInterface>, ValidatorInterface> */
    public function getValidators(): array
    {
        return $this->generalValidators;
    }

    public function addValidator(ValidatorInterface $validator): self
    {
        $this->generalValidators[get_class($validator)] = $validator;
        return $this;
    }

    public function hasValidator(string $className, bool $withException = false): bool
    {
        $exists = array_key_exists($className, $this->generalValidators);
        if (!$exists && $withException) {
            throw new RuntimeException(sprintf('The group does not contain %s validator.', $className));
        }
        return $exists;
    }

    public function getValidator(string $className): ValidatorInterface
    {
        $this->hasValidator($className, true);

        return $this->generalValidators[$className];
    }

    public function removeValidator(string $className): self
    {
        $this->hasValidator($className, true);
        unset($this->generalValidators[$className]);
        return $this;
    }

    /** @param array<array-key, ValidatorInterface> $validators */
    public function setValidators(array $validators): self
    {
        $this->generalValidators = [];
        return $this->addValidators($validators);
    }

    /** @param array<array-key, ValidatorInterface> $validators */
    public function addValidators(array $validators): self
    {
        foreach ($validators as $validator) {
            $this->addValidator($validator);
        }

        return $this;
    }

    public function isGeneralValid(): bool
    {
        if ($this->getValidators() === []) {
            return true;
        }

        $isValid = true;
        $data = $this->getData();
        $directErrors = [];
        $itemErrors = [];

        foreach ($this->getValidators() as $validator) {
            $validator->clearErrors();
            if ($validator->isValid($data)) {
                continue;
            }

            $isValid = false;
            foreach ($validator->getErrors() as $error) {
                $directErrors[] = $error;
            }

            // Keep errors on numbered child elements separate from the
            // numerically indexed root errors, and merge errors from distinct
            // validators targeting the same child element.
            foreach ($validator->getErrorBag()->getItems() as $key => $item) {
                $itemErrors[$key] ??= new ErrorBag();
                $itemErrors[$key]->merge($item);
            }

            if ($validator->isStop()) {
                break;
            }
        }

        // Preserve explicitly added root errors while moving their indexes
        // after item indexes. This avoids overwriting either kind of error.
        $previousErrors = [];
        foreach ($this->generalErrors as $key => $error) {
            if ($error instanceof Error) {
                $previousErrors[] = $error;
                unset($this->generalErrors[$key]);
            }
        }

        foreach ($itemErrors as $key => $item) {
            $this->generalErrors[$key] = $item->toArray();
        }
        foreach ([...$previousErrors, ...$directErrors] as $error) {
            $this->generalErrors[] = $error;
        }

        return $isValid;
    }

    /**
     * Merge nested validation errors without dropping either source when a
     * group validator and a child fieldset/collection use the same name.
     *
     * The existing public error layout is preserved. Only colliding branches
     * are combined; lists of direct errors are appended, not overwritten.
     *
     * @param array<int|string, mixed> $existing
     * @param array<int|string, mixed> $incoming
     * @return array<int|string, mixed>
     */
    protected static function mergeNestedErrors(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (!array_key_exists($key, $existing)) {
                $existing[$key] = $value;
                continue;
            }

            $previous = $existing[$key];
            if (is_array($previous) && is_array($value)) {
                $existing[$key] = $key === 'errors' && array_is_list($previous) && array_is_list($value)
                    ? [...$previous, ...$value]
                    : self::mergeNestedErrors($previous, $value);
                continue;
            }

            if (is_int($key) && ($previous instanceof Error || $value instanceof Error)) {
                // Direct Error entries and indexed item branches can share the
                // same numeric key. Keep both instead of replacing either one.
                $existing[] = $value;
                continue;
            }

            // This is a conflicting leaf from two validation sources. Keep
            // both values; callers can inspect either error without data loss.
            $existing[$key] = [$previous, $value];
        }

        return $existing;
    }

    /** @param list<int|string> $path */
    protected function syncOwnPath(array $path): void
    {
        $this->assignPath($path);
        $this->syncChildPaths();
    }

    /** @return iterable<string, Field|Fieldset|Collection> */
    protected function getDefinedElements(): iterable
    {
        yield from $this->fields;
        yield from $this->fieldsets;
        yield from $this->collections;
    }

    protected function syncChildPaths(): void
    {
        foreach ($this->getDefinedElements() as $element) {
            $this->syncChildPath($element);
        }
    }

    protected function syncChildPath(Field|Fieldset|Collection $element): void
    {
        $path = $this->getPath();
        $path[] = $element->getName();

        $element->setPath($path);
    }

    private function applyDefaultFieldFilters(Field $field): void
    {
        foreach ($this->defaultFieldFilters as $filter) {
            $this->applyDefaultFieldFilter($field, $filter);
        }
    }

    private function applyDefaultFieldFilter(Field $field, FilterInterface $filter): void
    {
        $field->inheritDefaultFilter(clone $filter);
    }

    /** @return array<class-string<FilterInterface>, FilterInterface> */
    private function cloneDefaultFieldFilters(): array
    {
        $filters = [];

        foreach ($this->defaultFieldFilters as $className => $filter) {
            $filters[$className] = clone $filter;
        }

        return $filters;
    }

    abstract public function setData(mixed $data): self;
    abstract public function getData(): mixed;
    abstract public function isValid(): bool;
}

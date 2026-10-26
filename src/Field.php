<?php

declare(strict_types=1);

namespace WilyCode\Form;

use RuntimeException;
use Psr\Http\Message\UploadedFileInterface;
use WilyCode\Filter\FilterInterface;
use WilyCode\Validator\Error;
use WilyCode\Validator\ErrorBag;
use WilyCode\Validator\ValidatorInterface;
use WilyCode\Form\Validators\OptionsAwareValidatorInterface;

class Field implements FieldInterface
{
    use HtmlPathTrait;

    private string $name;
    private mixed $data = null;
    /** @var array<int|string, mixed>|null */
    private ?array $options = null;
    private mixed $emptyValue = '';
    private bool $required;
    private bool $multiple = false;
    private bool $customValuesAllowed = false;
    private bool $invalidMultipleData = false;
    private ErrorBag $errors;

    /** @var array<class-string<ValidatorInterface>, ValidatorInterface> */
    private array $validators = [];

    /** @var array<class-string<FilterInterface>, FilterInterface> */
    private array $defaultFilters = [];

    /** @var array<class-string<FilterInterface>, true> */
    private array $localDefaultFilterClasses = [];

    /** @var array<class-string<FilterInterface>, true> */
    private array $excludedDefaultFilterClasses = [];

    /** @var array<class-string<FilterInterface>, FilterInterface> */
    private array $filters = [];

    public function __construct(string $name, bool $required = true)
    {
        $this->name = $name;
        $this->errors = new ErrorBag();
        $this->required = $required;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @param list<int|string> $path */
    public function setPath(array $path): self
    {
        $this->assignPath($path);
        return $this;
    }

    public function required(): self
    {
        $this->required = true;
        return $this;
    }

    public function optional(): self
    {
        $this->required = false;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isEmpty(): bool
    {
        if ($this->multiple && is_array($this->data)) {
            foreach ($this->data as $value) {
                if ($value !== null && $value !== '' && $value !== $this->getEmptyValue()
                    && !($value instanceof UploadedFileInterface && $value->getError() === UPLOAD_ERR_NO_FILE)) {
                    return false;
                }
            }

            return true;
        }

        return $this->data === null
            || $this->data === $this->getEmptyValue()
            || $this->data === []
            || ($this->data instanceof UploadedFileInterface && $this->data->getError() === UPLOAD_ERR_NO_FILE);
    }

    /** @return array<int|string, mixed> */
    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === $this->getEmptyValue()) {
            return [];
        }

        return [$value];
    }

    /** @return array<class-string<ValidatorInterface>, ValidatorInterface> */
    public function getValidators(): array
    {
        return $this->validators;
    }

    public function addValidator(ValidatorInterface $validator): self
    {
        $this->bindValidatorOptions($validator);
        $this->validators[$validator::class] = $validator;
        return $this;
    }

    public function hasValidator(string $className, bool $withException = false): bool
    {
        $exists = array_key_exists($className, $this->validators);
        if (!$exists && $withException) {
            throw new RuntimeException(sprintf('The field does not contain %s validator.', $className));
        }
        return $exists;
    }

    public function getValidator(string $className): ValidatorInterface
    {
        $this->hasValidator($className, true);
        return $this->validators[$className];
    }

    public function removeValidator(string $className): self
    {
        $this->hasValidator($className, true);
        unset($this->validators[$className]);
        return $this;
    }

    /** @param iterable<ValidatorInterface> $validators */
    public function setValidators(iterable $validators): self
    {
        $this->validators = [];
        return $this->addValidators($validators);
    }

    /** @param iterable<ValidatorInterface> $validators */
    public function addValidators(iterable $validators): self
    {
        foreach ($validators as $validator) {
            $this->addValidator($validator);
        }
        return $this;
    }

    private function bindValidatorOptions(ValidatorInterface $validator): void
    {
        if ($validator instanceof OptionsAwareValidatorInterface) {
            $validator->setOptions($this->options);
        }
    }

    /** @return array<class-string<FilterInterface>, FilterInterface> */
    public function getDefaultFilters(): array
    {
        return $this->defaultFilters;
    }

    /** @param iterable<FilterInterface> $filters */
    public function setDefaultFilters(iterable $filters): self
    {
        $this->defaultFilters = [];
        $this->localDefaultFilterClasses = [];
        $this->excludedDefaultFilterClasses = [];

        foreach ($filters as $filter) {
            $this->addDefaultFilter($filter);
        }

        return $this;
    }

    public function hasDefaultFilter(string $className): bool
    {
        return isset($this->defaultFilters[$className]);
    }

    public function getDefaultFilter(string $className): FilterInterface
    {
        if (!$this->hasDefaultFilter($className)) {
            throw new RuntimeException(sprintf('The field does not contain "%s" default filter.', $className));
        }

        return $this->defaultFilters[$className];
    }

    public function addDefaultFilter(FilterInterface $filter): self
    {
        $className = $filter::class;
        $this->defaultFilters[$className] = $filter;
        $this->localDefaultFilterClasses[$className] = true;
        unset($this->excludedDefaultFilterClasses[$className]);
        return $this;
    }

    /**
     * Remove one default filter from this field without affecting explicit addFilter() rules.
     * Also prevents an ancestor from reintroducing it during later synchronization.
     */
    public function removeDefaultFilter(string $className): self
    {
        if (!isset($this->defaultFilters[$className])) {
            throw new RuntimeException(sprintf('The field does not contain "%s" default filter.', $className));
        }

        unset($this->defaultFilters[$className], $this->localDefaultFilterClasses[$className]);
        $this->excludedDefaultFilterClasses[$className] = true;
        return $this;
    }

    /** @internal Used by form groups to propagate inherited default filters. */
    public function inheritDefaultFilter(FilterInterface $filter): void
    {
        $className = $filter::class;
        if (!isset($this->localDefaultFilterClasses[$className])
            && !isset($this->excludedDefaultFilterClasses[$className])) {
            $this->defaultFilters[$className] = $filter;
        }
    }

    /** @internal Used by form groups to withdraw inherited default filters. */
    public function removeInheritedDefaultFilter(string $className): void
    {
        if (!isset($this->localDefaultFilterClasses[$className])) {
            unset($this->defaultFilters[$className]);
        }
    }

    /** @return array<class-string<FilterInterface>, FilterInterface> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /** @param iterable<FilterInterface> $filters */
    public function setFilters(iterable $filters): self
    {
        $this->filters = [];
        return $this->addFilters($filters);
    }

    public function addFilter(FilterInterface $filter): self
    {
        $this->filters[$filter::class] = $filter;
        return $this;
    }

    public function getFilter(string $className): FilterInterface
    {
        $this->hasFilter($className, true);
        return $this->filters[$className];
    }

    public function hasFilter(string $className, bool $withException = false): bool
    {
        $exists = array_key_exists($className, $this->filters);
        if (!$exists && $withException) {
            throw new RuntimeException(sprintf('The field does not contain "%s" filter.', $className));
        }
        return $exists;
    }

    public function removeFilter(string $className): self
    {
        $this->hasFilter($className, true);
        unset($this->filters[$className]);
        return $this;
    }

    /** @param iterable<FilterInterface> $filters */
    public function addFilters(iterable $filters): self
    {
        foreach ($filters as $filter) {
            $this->addFilter($filter);
        }
        return $this;
    }

    public function setData(mixed $data): self
    {
        // A new payload invalidates errors produced for the previous value.
        $this->errors = new ErrorBag();
        foreach ($this->validators as $validator) {
            $validator->clearErrors();
        }

        if (!is_array($data)) {
            $data = [];
        }

        $value = array_key_exists($this->getName(), $data)
            ? $data[$this->getName()]
            : $this->getEmptyValue();

        $this->invalidMultipleData = false;
        if ($this->isMultiple()) {
            // Missing selections are empty, but a non-empty scalar is not a valid
            // multiple-value submission. Retain the normalized data shape while
            // recording the invalid input type for isValid().
            $this->invalidMultipleData = !is_array($value)
                && $value !== null
                && $value !== $this->getEmptyValue();
            $value = $this->toArray($value);
        }

        $value = $this->applyFilters($value, $this->getEffectiveFilters());
        if ($this->isMultiple() && !is_array($value)) {
            $this->invalidMultipleData = true;
        }
        $this->data = $value;
        return $this;
    }

    /** @return array<class-string<FilterInterface>, FilterInterface> */
    private function getEffectiveFilters(): array
    {
        $filters = $this->defaultFilters;

        foreach ($this->filters as $className => $filter) {
            $filters[$className] = $filter;
        }

        return $filters;
    }

    /** @param iterable<FilterInterface> $filters */
    private function applyFilters(mixed $data, iterable $filters): mixed
    {
        foreach ($filters as $filter) {
            $data = $filter->getFilteredValue($data);
        }
        return $data;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getEmptyValue(): mixed
    {
        return $this->emptyValue;
    }

    public function setEmptyValue(mixed $emptyValue): self
    {
        $this->emptyValue = $emptyValue;
        return $this;
    }

    public function hasOptions(): bool
    {
        return is_array($this->options);
    }

    /** @return array<int|string, mixed>|null */
    public function getOptions(): ?array
    {
        return $this->options;
    }

    /** @param array<int|string, mixed>|null $options */
    public function options(?array $options): self
    {
        $this->options = $options;

        foreach ($this->validators as $validator) {
            $this->bindValidatorOptions($validator);
        }

        return $this;
    }

    /** @return list<mixed> */
    public function getSelectedOptions(): array
    {
        if (!$this->hasOptions()) {
            return [];
        }

        return array_values(
            (new Options($this->options ?? []))->selected($this->toArray($this->getData())),
        );
    }

    /** @return list<mixed> */
    public function getCustomValues(): array
    {
        return array_values($this->getCustomValuesWithIndexes());
    }

    /** @return array<int|string, mixed> */
    public function getCustomValuesWithIndexes(): array
    {
        $values = $this->toArray($this->getData());
        if (!$this->hasOptions()) {
            return $values;
        }

        return (new Options($this->options ?? []))->custom($values);
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function multiple(): self
    {
        $this->multiple = true;
        return $this;
    }

    public function single(): self
    {
        $this->multiple = false;
        return $this;
    }

    private function validateDefault(): bool
    {
        $value = $this->getData();

        if ($this->invalidMultipleData || ($this->isMultiple() && !is_array($value))) {
            $this->errors->addError(new Error('validation.array.invalid_type'));
            return false;
        }

        // A multiple field represents a flat selection list. Nested arrays
        // (including empty ones) are malformed values, not chosen options.
        if ($this->isMultiple()) {
            foreach ($value as $selectedValue) {
                if (is_array($selectedValue)) {
                    $this->errors->addError(new Error('validation.array.invalid_type'));
                    return false;
                }
            }
        }

        if ($this->isRequired()) {
            if ($this->isEmpty()) {
                $this->errors->addError(new Error('validation.not_empty.required'));
                return false;
            }
        } elseif ($this->isEmpty()) {
            return true;
        }

        if (!$this->allowsCustomValues() && $this->hasOptions()) {
            $options = new Options($this->getOptions() ?? []);
            $values = is_array($value) ? $value : [$value];

            foreach ($values as $single) {
                if (!$options->contains($single, strict: false)) {
                    $this->errors->addError(new Error('validation.in_options.invalid_option'));
                    return false;
                }
            }
        }

        return true;
    }

    public function isValid(): bool
    {
        $this->errors = new ErrorBag();

        if (!$this->validateDefault()) {
            return false;
        }

        if ($this->isEmpty()) {
            return true;
        }

        foreach ($this->getValidators() as $validator) {
            if (!$this->validateValue($this->getData(), $validator, $this->errors)
                && $validator->isStop()) {
                return false;
            }
        }

        return !$this->errors->hasErrors();
    }

    private function validateValue(mixed $value, ValidatorInterface $validator, ErrorBag $bag): bool
    {
        $validator->clearErrors();
        if ($validator->isValid($value)) {
            return true;
        }

        $bag->merge($validator->getErrorBag());
        return false;
    }

    public function getErrors(): ErrorBag
    {
        return $this->errors;
    }

    public function allowsCustomValues(): bool
    {
        return $this->customValuesAllowed;
    }

    public function allowCustomValues(): self
    {
        $this->customValuesAllowed = true;
        return $this;
    }

    public function disallowCustomValues(): self
    {
        $this->customValuesAllowed = false;
        return $this;
    }

    /**
     * @param array<class-string<ValidatorInterface>, ValidatorInterface> $validators
     * @return array<class-string<ValidatorInterface>, ValidatorInterface>
     */
    private static function newValidators(array $validators): array
    {
        $clones = [];

        foreach ($validators as $className => $validator) {
            $clones[$className] = (clone $validator)->clearErrors();
        }

        return $clones;
    }

    /**
     * @param array<class-string<FilterInterface>, FilterInterface> $filters
     * @return array<class-string<FilterInterface>, FilterInterface>
     */
    private static function newFilters(array $filters): array
    {
        $clones = [];

        foreach ($filters as $className => $filter) {
            $clones[$className] = clone $filter;
        }

        return $clones;
    }

    public static function createNew(Field $field): Field
    {
        // Cloning preserves custom Field subclasses and their configuration.
        $newField = clone $field;
        $newField->data = null;
        $newField->invalidMultipleData = false;
        $newField->errors = new ErrorBag();
        $newField->defaultFilters = self::newFilters($field->defaultFilters);
        $newField->filters = self::newFilters($field->filters);
        $newField->validators = self::newValidators($field->validators);

        return $newField;
    }
}

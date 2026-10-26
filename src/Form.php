<?php

declare(strict_types=1);

namespace WilyCode\Form;

use InvalidArgumentException;
use OutOfBoundsException;
use Psr\Http\Message\ServerRequestInterface;
use WilyCode\Validator\Error;
use WilyCode\Form\Error\ErrorException;

abstract class Form extends AbstractFormGroup
{
    private FormMethod $method = FormMethod::Post;

    private bool $isSubmitted = false;

    public function __construct()
    {
        $this->setPath([]);
        $this->init();
    }

    public function setRequest(ServerRequestInterface $request): static
    {
        $this->isSubmitted = strtoupper($request->getMethod()) === $this->getMethod()->value;
        if ($this->isSubmitted) {
            $data = $this->getMethod() === FormMethod::Get
                ? $request->getQueryParams()
                : self::mergeRequestData((array) $request->getParsedBody(), $request->getUploadedFiles());
            $this->setData($data);
        }
        return $this;
    }

    /**
     * Uploaded files override body fields; matching associative fieldsets merge,
     * including matching numeric-indexed collection rows. Empty arrays replace
     * the original value. Query parameters are never imported for non-GET forms.
     *
     * @param array<int|string, mixed> $body
     * @param array<int|string, mixed> $files
     * @return array<int|string, mixed>
     */
    private static function mergeRequestData(array $body, array $files): array
    {
        foreach ($files as $name => $value) {
            if (isset($body[$name]) && is_array($body[$name]) && is_array($value)
                && $value !== []) {
                $body[$name] = self::mergeRequestData($body[$name], $value);
            } else {
                $body[$name] = $value;
            }
        }

        return $body;
    }

    public function isSubmitted(): bool
    {
        return $this->isSubmitted;
    }

    /**
     * Validate only when the form has been submitted.
     */
    public function isSubmittedAndValid(): bool
    {
        return $this->isSubmitted() && $this->isValid();
    }

    public function setData(mixed $data = []): static
    {
        if (!is_array($data)) {
            throw new InvalidArgumentException('Form data must be an array.');
        }

        $this->clearAddedGeneralErrors();
        $this->resetValidationErrors();

        foreach ($this->getFilters() as $filter) {
            $data = $filter->getFilteredValue($data);
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('Form filters must return an array.');
        }

        foreach ($this->getDefinedElements() as $element) {
            $element->setData($data);
        }

        return $this;
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        $values = [];
        foreach ($this->getDefinedElements() as $name => $element) {
            $values[$name] = $element->getData();
        }

        return $values;
    }

    public function getDataByName(string $name): mixed
    {
        if (array_key_exists($name, $this->fields)) {
            return $this->fields[$name]->getData();
        }

        if (array_key_exists($name, $this->fieldsets)) {
            return $this->fieldsets[$name]->getData();
        }

        if (array_key_exists($name, $this->collections)) {
            return $this->collections[$name]->getData();
        }

        throw new OutOfBoundsException(sprintf('Form element "%s" does not exist.', $name));
    }

    /**
     * @param list<string> $excludes
     * @return array<string, mixed>
     */
    public function getDataWithExcludes(array $excludes = []): array
    {
        $values = [];
        foreach ($this->getData() as $name => $value) {
            if (in_array($name, $excludes, true)) {
                continue;
            }
            $values[$name] = $value;
        }
        return $values;
    }

    /** @throws ErrorException */
    public function assertValid(): void
    {
        if (!$this->isValid()) {
            throw new ErrorException($this->getErrors(), $this->getGeneralErrors());
        }
    }

    public function isValid(): bool
    {
        $this->resetValidationErrors();

        $isValid = true;

        if (!$this->isGeneralValid()) {
            $isValid = false;
        }

        foreach ($this->getDefinedElements() as $name => $element) {
            if (!$element->isValid()) {
                $isValid = false;
            }

            if ($element instanceof AbstractFormGroup) {
                $this->generalErrors[$name] = self::mergeNestedErrors(
                    is_array($this->generalErrors[$name] ?? null) ? $this->generalErrors[$name] : [],
                    $element->getGeneralErrors(),
                );
            }

            $this->errors[$name] = $element->getErrors();
        }

        foreach ($this->getGeneralErrors() as $generalError) {
            if ($generalError instanceof Error) {
                $isValid = false;
                break;
            }
        }

        return $isValid;
    }

    public function getMethod(): FormMethod
    {
        return $this->method;
    }

    public function setMethod(FormMethod $method): self
    {
        $this->method = $method;
        return $this;
    }

    abstract protected function init(): void;
}

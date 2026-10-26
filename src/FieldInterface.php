<?php

declare(strict_types=1);

namespace WilyCode\Form;

use WilyCode\Validator\ErrorBag;

interface FieldInterface
{
    public function getName(): string;

    /**
     * @return list<int|string>
     */
    public function getPath(): array;

    public function getPathString(string $separator = '.'): string;

    public function getHtmlName(): string;

    public function getHtmlId(string $separator = '_'): string;

    public function getData(): mixed;

    public function setData(mixed $data): self;

    public function isRequired(): bool;

    public function isValid(): bool;

    /**
     * Field returns ErrorBag. Fieldset and Collection return nested error arrays.
     *
     * @return ErrorBag|array<int|string, mixed>
     */
    public function getErrors(): ErrorBag|array;
}

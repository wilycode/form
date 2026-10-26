<?php

declare(strict_types=1);

namespace WilyCode\Form\Error;

use Exception;
use WilyCode\Validator\Error;

class ErrorException extends Exception
{
    /** @var array<int|string, mixed> */
    private array $errors;

    /** @var array<int|string, Error|array<int|string, mixed>> */
    private array $generalErrors;

    /**
     * @param array<int|string, mixed> $errors
     * @param array<int|string, Error|array<int|string, mixed>> $generalErrors
     */
    public function __construct(array $errors, array $generalErrors)
    {
        $this->errors = $errors;
        $this->generalErrors = $generalErrors;
        parent::__construct();
    }

    /** @return array<int|string, mixed> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<int|string, Error|array<int|string, mixed>> */
    public function getGeneralErrors(): array
    {
        return $this->generalErrors;
    }

}
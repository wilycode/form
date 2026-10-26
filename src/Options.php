<?php

declare(strict_types=1);

namespace WilyCode\Form;

final class Options
{
    /** @param array<int|string, mixed> $values */
    public function __construct(
        private readonly array $values,
    ) {
    }

    public function contains(mixed $value, bool $strict = true): bool
    {
        if (array_is_list($this->values)) {
            foreach ($this->values as $option) {
                if (self::matches($value, $option, $strict)) {
                    return true;
                }
            }

            return false;
        }

        if (!is_int($value) && !is_string($value)) {
            return false;
        }

        if (!$strict) {
            // Non-strict mode accepts HTML string submissions for numeric keys.
            return array_key_exists($value, $this->values);
        }

        // array_key_exists('1', [1 => 'Active']) is true in PHP because
        // numeric string keys are coerced to integers. Strict matching must
        // compare the original submitted type with the actual key type.
        foreach (array_keys($this->values) as $key) {
            if ($key === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Avoid PHP's loose boolean comparison, where every non-empty string
     * compares equal to true. Numeric strings still match numeric options.
     */
    private static function matches(mixed $value, mixed $option, bool $strict): bool
    {
        if ($strict || $value === $option) {
            return $value === $option;
        }

        if (is_bool($option)) {
            // HTML form controls submit strings, not PHP booleans.
            // Accept only explicit boolean representations.
            return match ($value) {
                '1', 1, 'true' => $option === true,
                '0', 0, 'false' => $option === false,
                default => false,
            };
        }

        if (is_bool($value)) {
            return false;
        }

        // PHP loose comparisons treat distinct numeric-looking identifiers
        // (e.g. "0e123" == "0e456", or "0e123" == 0) as equal.
        // Compare all string IDs exactly, allowing only the canonical HTML
        // string representation of integer/float option values.
        if (is_string($value) && is_string($option)) {
            return $value === $option;
        }
        if (is_string($value) && (is_int($option) || is_float($option))) {
            return $value === (string) $option;
        }
        if (is_string($option) && (is_int($value) || is_float($value))) {
            return $option === (string) $value;
        }

        return (is_int($value) || is_float($value))
            && (is_int($option) || is_float($option))
            && $value == $option;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int|string, mixed>
     */
    public function selected(array $values): array
    {
        return array_filter(
            $values,
            fn(mixed $value): bool => $this->contains($value, strict: false),
        );
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int|string, mixed>
     */
    public function custom(array $values): array
    {
        return array_filter(
            $values,
            fn(mixed $value): bool => !$this->contains($value, strict: false),
        );
    }
}

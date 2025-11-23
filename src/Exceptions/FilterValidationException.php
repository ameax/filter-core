<?php

namespace Ameax\FilterCore\Exceptions;

use Exception;
use Illuminate\Support\MessageBag;

/**
 * Exception thrown when filter value validation fails.
 */
class FilterValidationException extends Exception
{
    /**
     * @param  array<string, array<string>>  $errors
     */
    public function __construct(
        protected string $filterKey,
        protected array $errors,
        string $message = '',
    ) {
        $message = $message ?: "Validation failed for filter '{$filterKey}': ".implode(', ', $this->getFirstErrors());
        parent::__construct($message);
    }

    /**
     * Get the filter key that failed validation.
     */
    public function getFilterKey(): string
    {
        return $this->filterKey;
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get validation errors as a MessageBag.
     */
    public function getMessageBag(): MessageBag
    {
        return new MessageBag($this->errors);
    }

    /**
     * Get the first error message for each field.
     *
     * @return array<string>
     */
    public function getFirstErrors(): array
    {
        return array_map(fn (array $messages) => $messages[0] ?? '', $this->errors);
    }
}

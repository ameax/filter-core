<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Resolution;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * One condition that cannot be applied, and where it sits in the selection.
 *
 * The path lists the item positions from the root group down, e.g. [0, 2] is the
 * third item of the first nested group.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Diagnostic implements Arrayable, JsonSerializable
{
    /**
     * @param  list<int>  $path
     * @param  array<string, mixed>  $details  e.g. validation errors
     */
    public function __construct(
        public DiagnosticCode $code,
        public array $path,
        public string $filterKey,
        public string $mode,
        public mixed $value,
        public string $message,
        public array $details = [],
    ) {}

    /**
     * @return array{code: string, path: list<int>, filter: string, mode: string, value: mixed, message: string, details: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'path' => $this->path,
            'filter' => $this->filterKey,
            'mode' => $this->mode,
            'value' => $this->value,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

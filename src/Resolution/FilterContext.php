<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Resolution;

/**
 * Everything a filter may need beyond its value: the policy, who is asking, in
 * which time zone, plus anything the application adds (tenant, permissions, roles).
 *
 * Filters receive it when they implement ContextAwareFilter.
 */
final readonly class FilterContext
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public ResolutionPolicy $policy = ResolutionPolicy::Strict,
        public mixed $user = null,
        public ?string $timezone = null,
        public array $attributes = [],
    ) {}

    public static function strict(): self
    {
        return new self(ResolutionPolicy::Strict);
    }

    public static function lenient(): self
    {
        return new self(ResolutionPolicy::Lenient);
    }

    public function withPolicy(ResolutionPolicy $policy): self
    {
        return new self($policy, $this->user, $this->timezone, $this->attributes);
    }

    public function withUser(mixed $user): self
    {
        return new self($this->policy, $user, $this->timezone, $this->attributes);
    }

    public function withAttribute(string $key, mixed $value): self
    {
        return new self($this->policy, $this->user, $this->timezone, [$key => $value] + $this->attributes);
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function isStrict(): bool
    {
        return $this->policy === ResolutionPolicy::Strict;
    }
}

<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use InvalidArgumentException;

/**
 * Factory class for creating match mode instances.
 *
 * Provides static factory methods for all built-in match modes
 * and supports custom match modes via registration.
 *
 * @method static IsMatchMode is()
 * @method static IsNotMatchMode isNot()
 * @method static ContainsMatchMode contains()
 * @method static AnyMatchMode any()
 * @method static NoneMatchMode none()
 * @method static GreaterThanMatchMode greaterThan()
 * @method static GreaterThanMatchMode gt()
 * @method static LessThanMatchMode lessThan()
 * @method static LessThanMatchMode lt()
 * @method static BetweenMatchMode between()
 * @method static EmptyMatchMode empty()
 * @method static NotEmptyMatchMode notEmpty()
 */
class MatchMode
{
    /**
     * Registered match mode classes.
     *
     * @var array<string, class-string<MatchModeContract>>
     */
    protected static array $modes = [];

    /**
     * Whether default modes have been registered.
     */
    protected static bool $defaultsRegistered = false;

    /**
     * Register a custom match mode.
     *
     * @param  class-string<MatchModeContract>  $class
     */
    public static function register(string $name, string $class): void
    {
        self::$modes[$name] = $class;
    }

    /**
     * Register multiple match modes at once.
     *
     * @param  array<string, class-string<MatchModeContract>>  $modes
     */
    public static function registerMany(array $modes): void
    {
        foreach ($modes as $name => $class) {
            self::register($name, $class);
        }
    }

    /**
     * Get a match mode instance by key.
     *
     * @throws InvalidArgumentException When match mode is not found
     */
    public static function get(string $key): MatchModeContract
    {
        self::ensureDefaultsRegistered();

        if (! isset(self::$modes[$key])) {
            throw new InvalidArgumentException("Unknown match mode: {$key}");
        }

        return new self::$modes[$key]();
    }

    /**
     * Check if a match mode is registered.
     */
    public static function has(string $key): bool
    {
        self::ensureDefaultsRegistered();

        return isset(self::$modes[$key]);
    }

    /**
     * Get all registered match mode keys.
     *
     * @return array<string>
     */
    public static function keys(): array
    {
        self::ensureDefaultsRegistered();

        return array_keys(self::$modes);
    }

    /**
     * Reset to default modes (useful for testing).
     */
    public static function reset(): void
    {
        self::$modes = [];
        self::$defaultsRegistered = false;
    }

    /**
     * Handle dynamic static method calls.
     *
     * Converts method names to match mode keys:
     * - is() → 'is'
     * - isNot() → 'isNot'
     * - greaterThan() → 'greaterThan'
     *
     * @param  array<mixed>  $arguments
     */
    public static function __callStatic(string $name, array $arguments): MatchModeContract
    {
        self::ensureDefaultsRegistered();

        if (isset(self::$modes[$name])) {
            return new self::$modes[$name]();
        }

        throw new InvalidArgumentException("Unknown match mode: {$name}");
    }

    /**
     * Ensure default match modes are registered.
     */
    protected static function ensureDefaultsRegistered(): void
    {
        if (self::$defaultsRegistered) {
            return;
        }

        self::registerDefaults();
        self::$defaultsRegistered = true;
    }

    /**
     * Register all default match modes.
     */
    protected static function registerDefaults(): void
    {
        self::$modes = array_merge([
            // Primary names
            'is' => IsMatchMode::class,
            'isNot' => IsNotMatchMode::class,
            'contains' => ContainsMatchMode::class,
            'any' => AnyMatchMode::class,
            'none' => NoneMatchMode::class,
            'greaterThan' => GreaterThanMatchMode::class,
            'lessThan' => LessThanMatchMode::class,
            'between' => BetweenMatchMode::class,
            'empty' => EmptyMatchMode::class,
            'notEmpty' => NotEmptyMatchMode::class,

            // Aliases (short forms)
            'gt' => GreaterThanMatchMode::class,
            'lt' => LessThanMatchMode::class,
            'is_not' => IsNotMatchMode::class,
            'not_empty' => NotEmptyMatchMode::class,
        ], self::$modes);
    }
}

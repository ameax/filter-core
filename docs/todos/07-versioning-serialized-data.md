# TODO: No Versioning for Serialized Data

**Priority:** Low
**Status:** Open

## Problem

When filter schemas change, old saved selections may break. There's no version field in the JSON format to handle migrations.

Example scenarios that could break:
- A filter is renamed (e.g., `StatusFilter` → `UserStatusFilter`)
- A match mode key changes (e.g., `is_not` standardized to `isNot`)
- A filter's allowed modes change (e.g., TextFilter no longer supports `gt`)
- Filter options change (e.g., 'pending' status removed)

## Current Situation

```json
{
  "filters": [
    {"filter": "StatusFilter", "mode": "is", "value": "active"}
  ]
}
```

No version information means we can't:
- Detect if JSON was created with an old schema
- Apply migrations automatically
- Warn users about incompatible selections

## Proposed Solution

Add a version field to JSON serialization:

```json
{
  "version": "1.0",
  "filters": [
    {"filter": "StatusFilter", "mode": "is", "value": "active"}
  ]
}
```

### Version Format

Use semantic versioning:
- **Major**: Breaking changes to JSON structure
- **Minor**: New features, backward compatible
- **Patch**: Bug fixes, no format changes

### Implementation

```php
class FilterSelection
{
    public const VERSION = '1.0';

    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'name' => $this->name,
            'description' => $this->description,
            'filters' => $this->filters->map->toArray()->all(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $version = $data['version'] ?? null;

        // Handle version-specific deserialization
        return match($version) {
            '1.0' => static::fromV1Array($data),
            null => static::fromLegacyArray($data), // No version = legacy
            default => throw new UnsupportedVersionException(
                "FilterSelection version {$version} is not supported"
            ),
        };
    }

    protected static function fromLegacyArray(array $data): self
    {
        // Handle old format without version
        // Apply any necessary migrations
        return static::fromV1Array(static::migrateLegacyToV1($data));
    }

    protected static function migrateLegacyToV1(array $data): array
    {
        // Example migration: rename old match mode keys
        // 'is_not' → 'isNot'
        // etc.
        return $data;
    }
}
```

### Migration Registry

For complex migrations, use a registry:

```php
class FilterSelectionMigrator
{
    protected array $migrations = [
        '0.9' => MigrateV09ToV10::class,
        '1.0' => MigrateV10ToV11::class,
    ];

    public function migrate(array $data, string $targetVersion = FilterSelection::VERSION): array
    {
        $currentVersion = $data['version'] ?? '0.9';

        foreach ($this->migrations as $fromVersion => $migrationClass) {
            if (version_compare($currentVersion, $fromVersion, '<=')) {
                $data = (new $migrationClass)->migrate($data);
            }
        }

        return $data;
    }
}
```

### Example Migration Class

```php
class MigrateV09ToV10
{
    public function migrate(array $data): array
    {
        // Rename match mode keys
        foreach ($data['filters'] ?? [] as &$filter) {
            $filter['mode'] = $this->migrateMatchMode($filter['mode']);
        }

        $data['version'] = '1.0';
        return $data;
    }

    protected function migrateMatchMode(string $mode): string
    {
        return match($mode) {
            'is_not' => 'isNot',
            'not_empty' => 'notEmpty',
            'starts_with' => 'startsWith',
            'ends_with' => 'endsWith',
            default => $mode,
        };
    }
}
```

## Benefits

1. **Forward compatibility** - Can detect and reject future versions
2. **Backward compatibility** - Can migrate old versions automatically
3. **Clear errors** - Users know when a selection is incompatible
4. **Safe refactoring** - Changes can be tracked and migrated

## Considerations

- **Database storage**: If using FilterPreset model (see TODO #04), add a `version` column
- **Testing**: Each migration needs tests
- **Documentation**: Document breaking changes in CHANGELOG with migration notes
- **Performance**: Migrations should be fast (mostly array operations)

## Related Files

- `src/Selections/FilterSelection.php` - Add version constant and migration logic
- `src/Selections/FilterSelectionMigrator.php` (new) - Migration registry
- Create `src/Migrations/` directory for migration classes
- Update tests to verify version handling

## Related TODOs

- #04 (Database Persistence) - FilterPreset should store version
- #01 (Serialization Context) - Metadata could include version info

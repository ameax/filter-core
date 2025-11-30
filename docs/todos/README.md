# Open Issues & Future Improvements

This directory contains individual TODO items for the filter-core package. Each file represents one specific issue or enhancement.

## Summary

**All high-priority items completed!**

**Recently completed**:
- DateFilter with comprehensive DateRangeValue system (relative dates, specific periods, fiscal years, etc.)
- DateFilter timezone handling for DATETIME/TIMESTAMP columns via `hasTime()`
- DecimalFilter with `storedAsInteger()` support for price/cents-style columns
- QuickFilterPresets for user-definable date range presets with database persistence

**Low-priority items**: Developer experience improvements and advanced features.

## Issues by Priority

### Recently Completed

- **[DateFilter Type](09-date-filter.md)** - Comprehensive date filtering with DateRangeValue, relative dates, fiscal years, etc.
- **[DateTimeFilter Type](10-datetime-filter.md)** - Timezone handling integrated into DateFilter via `hasTime()`
- **[DecimalFilter Type](11-decimal-filter.md)** - Filter type for DECIMAL/FLOAT columns with `storedAsInteger()` support
- **[QuickFilterPresets](10-quick-filter-presets.md)** - Database-driven quick date range presets
- **[Naming Conventions](05-inconsistent-naming.md)** - Documented in `docs/guides/99-naming-conventions.md`
- **[Debugging Tools](06-debugging-tools.md)** - `toSql()`, `toSqlWithBindings()`, `explain()`, `debug()`, `dd()` on FilterSelection

### Low Priority

1. **[Versioning Serialized Data](07-versioning-serialized-data.md)** - No version field in JSON for migration support
2. **[Filter Dependencies](08-filter-dependencies.md)** - No dependency/visibility system for complex UIs

## Status Legend

- **Open** - Not yet implemented
- **Completed** - Fully implemented
- **In Progress** - Currently being worked on
- **Partial** - Partially implemented or has workarounds
- **Blocked** - Waiting on external dependency

## Contributing

When working on a TODO:

1. Update the status in the file
2. Add implementation notes
3. Reference related PRs
4. Update this README when complete

When a TODO is fully resolved:

1. Move the file to `docs/resolved/` or delete if no longer relevant
2. Update the architecture-analysis.md with resolution details
3. Add to CHANGELOG.md

## Related Documentation

- [Architecture Analysis](../architecture-analysis.md) - Historical analysis of design decisions
- [Guides](../guides/) - User-facing documentation
- [Concept](../concept/) - Future package concepts (ui-adapters)

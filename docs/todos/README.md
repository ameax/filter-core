# Open Issues & Future Improvements

This directory contains individual TODO items for the filter-core package. Each file represents one specific issue or enhancement.

## Summary

**High-priority items**: Three new filter types (Date, DateTime, Decimal) needed for common database column types. These include relative date/time expressions like "older than 30 days" or "last year" for better user experience.

**Low-priority items**: Developer experience improvements and advanced features.

## Issues by Priority

### High Priority

1. **[DateFilter Type](09-date-filter.md)** - No built-in filter type for DATE columns
2. **[DateTimeFilter Type](10-datetime-filter.md)** - No built-in filter type for DATETIME/TIMESTAMP columns
3. **[DecimalFilter Type](11-decimal-filter.md)** - No built-in filter type for DECIMAL/FLOAT columns

### Medium Priority

✅ **All medium-priority issues resolved!**

### Low Priority

1. **[Inconsistent Naming](05-inconsistent-naming.md)** - Mixed naming conventions across the codebase
2. **[Debugging Tools](06-debugging-tools.md)** - Missing SQL preview, explain(), trace capabilities
3. **[Versioning Serialized Data](07-versioning-serialized-data.md)** - No version field in JSON for migration support
4. **[Filter Dependencies](08-filter-dependencies.md)** - No dependency/visibility system for complex UIs

## Status Legend

- **Open** - Not yet implemented
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

1. Move the file to `docs/resolved/`
2. Update the architecture-analysis.md with resolution details
3. Add to CHANGELOG.md

## Related Documentation

- [Architecture Analysis](../architecture-analysis.md) - Historical analysis of design decisions
- [Guides](../guides/) - User-facing documentation
- [Concept](../concept/) - Future package concepts (ui-adapters)

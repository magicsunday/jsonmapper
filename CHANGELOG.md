## Unreleased

### Added
- Introduced `JsonMapper::createWithDefaults()` to bootstrap the mapper with Symfony reflection, PhpDoc extractors, and a default property accessor.

### Changed
- Marked `MagicSunday\\JsonMapper\\JsonMapper` as `final` and promoted constructor dependencies to `readonly` properties for consistent visibility.
- Declared `MagicSunday\\JsonMapper\\Converter\\CamelCasePropertyNameConverter` as `final` and immutable.

### Documentation
- Added a quick start walkthrough and guidance on type converters, error strategies, and performance tuning to the README.
- Published an API reference (`docs/API.md`) and new recipe guides for enums, attributes, nested collections, and custom name converters.

# Changelog

All notable changes to WilyCode Form are documented here.

## [0.1.0] - 2026-10-26

### Added

- Framework-independent form tree with fields, fieldsets, repeated collections and typed data access.
- Filters at field, group and default-field scope, with removal and inheritance APIs.
- Reusable validation rules, structured errors and group/collection error paths.
- PSR-7 request support, upload data merging, and `isSubmittedAndValid()`.
- WilyCode Filter/Validator contracts integration; optional ready-made implementations.
- Automated PHPUnit tests and CI for PHP 8.4/8.5.

### Reliability and safety

- Validate malformed nested data and multiple-choice submissions instead of silently accepting them.
- Preserve required versus optional nested-fieldset behavior after normalization.
- Keep string option identifiers distinct (`0e123` must not equal `0e456`); accept canonical HTML strings for numeric options.
- Avoid PHP loose-boolean matching and enforce exact types for strict associative options.
- Ensure cloned collection rows have independent mutable filters and validator error state.
- Rebind row-local `FieldCompareValidator` contexts when cloning nested structures.
- Preserve manual errors correctly, and clear stale validation errors when new data is submitted.
- Prevent conflicting field, fieldset and collection definitions within the same group.
- Add cross-package regression coverage for `SlugFilter` and `RegexValidator`.

### Documentation

- Describe filter scopes, Add/Edit inheritance, nested collection rules, request precedence, options and optional extensions.

# WilyCode Form

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![MIT License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/form/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/form/actions/workflows/tests.yml)

**Framework-independent PHP 8.4 form handling with typed fields, nested fieldsets, collections, filters and validation.**

> ### WilyCode PHP Ecosystem
>
> This library is part of **[WilyCode](https://github.com/wilycode/wilycode)**, a collection of independent PHP components. Each package has its own repository, Composer installation and releases.
>
> **[Explore the WilyCode PHP Ecosystem →](https://github.com/wilycode/wilycode)**

## Contents

- [Installation](#installation)
- [Quick start](#quick-start)
- [Submitting and validating](#submitting-and-validating)
- [Filtering and validators](#filtering-and-validators)
- [Inheriting Add/Edit forms](#inheriting-addedit-forms)
- [Nested fieldsets and collections](#nested-fieldsets-and-collections)
- [Replacing field definitions](#replacing-field-definitions)
- [Request data precedence](#request-data-precedence)
- [Transparent fields](#transparent-fields)
- [Errors and form data](#errors-and-form-data)
- [Optional extensions](#optional-extensions)
- [API reference](#api-reference)
- [Development](#development)

## Installation

Requirements: **PHP 8.4+**, `psr/http-message`, `wilycode/filter` and `wilycode/validator` (the latter two are installed automatically).

```bash
composer require wilycode/form:^0.1
```

The core package contains the form tree and its integration with filter and validator **contracts**. For the ready-made rules used in the examples, install:

```bash
composer require wilycode/filters:^0.1 wilycode/validators:^0.1
```

`wilycode/filters` and `wilycode/validators` are optional: applications may implement `FilterInterface` and `ValidatorInterface` themselves. Use a PSR-7 server request implementation for `setRequest()`.

## Quick start

```php
<?php

use WilyCode\Filters\NullFilter;
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Form;
use WilyCode\Validators\StringLengthValidator;

final class ArticleForm extends Form
{
    protected function init(): void
    {
        $this->addDefaultFieldFilter(new TrimFilter());

        $this->addField('title')
            ->addValidator(new StringLengthValidator()->setMin(3));

        $this->addField('excerpt', required: false)
            ->setEmptyValue(null)
            ->addFilter(new NullFilter());
    }
}

$form = (new ArticleForm())->setRequest($request); // PSR-7 ServerRequestInterface

if ($form->isSubmittedAndValid()) {
    $data = $form->getData();
    // Persist the validated data in your application.
}
```

An `ArticleForm` instance is configured through `init()`. The form's default HTTP method is `POST`; use `$this->setMethod(FormMethod::Get)` inside `init()` when needed.

## Submitting and validating

| Method | Behavior |
| --- | --- |
| `isSubmitted()` | Reports whether the request method matches the configured form method. |
| `isValid()` | Validates the current data, independently of the submission state. |
| `isSubmittedAndValid()` | Short-circuits: validates only when submitted. Equivalent to `isSubmitted() && isValid()`. |
| `assertValid()` | Validates and throws `ErrorException` on failure; **does not** check submission. |
| `setData(array $data)` | Sets field data directly without an HTTP request; useful for initial data and tests. |
| `setRequest($request)` | Reads submitted PSR-7 request data using the rules described below. |

For an initial GET request to a POST form, `isSubmittedAndValid()` returns `false` without triggering validation errors. Directly calling `isValid()` may produce errors for required but empty fields.

## Filtering and validators

The library distinguishes **three levels** of filters:

| Scope | Add | Inspect | Remove |
| --- | --- | --- | --- |
| Individual field | `addFilter($filter)` | `hasFilter(Foo::class)`, `getFilter(Foo::class)` | `removeFilter(Foo::class)` |
| Default for child fields | `addDefaultFieldFilter($filter)` on a group; `addDefaultFilter($filter)` on a field | `hasDefaultFieldFilter()` / `getDefaultFieldFilter()` on a group; `hasDefaultFilter()` / `getDefaultFilter()` on a field | `removeDefaultFieldFilter()` on a group; `removeDefaultFilter()` on a field |
| Complete group data array | `addFilter($filter)` on `Form`, `Fieldset` or `Collection` | `hasFilter(Foo::class)`, `getFilter(Foo::class)` | `removeFilter(Foo::class)` |

A group-level `addFilter()` transforms the **complete array** submitted to that group; it does *not* apply the filter separately to each child field. For that, use `addDefaultFieldFilter()`.

For list options containing booleans (`[false, true]`), a value must be an actual boolean or an explicit HTML representation (`'0'`, `'1'`, `'false'`, `'true'`). Arbitrary non-empty strings are rejected; PHP's loose comparison would otherwise treat them as `true`. Non-strict numeric option matching remains available for HTML submissions (for example, `'1'` matches `1`). String option identifiers are compared exactly, including numeric-looking strings such as `0e123` and `0e456`. Integer and float options accept canonical HTML string representations (`'1'` for `1`), but not alternate numeric strings such as `'01'` or `'1e0'`. The `strict` flag on `InOptionsValidator` requires exact types for both list values and associative-map keys. For example, `[1 => 'Active', 2 => 'Disabled']` with `strict: true` accepts integer `1` but rejects HTML string `'1'`; the default non-strict mode accepts both. In PHP, `[0 => 'No', 1 => 'Yes']` is a list, so option matching uses its **values**, not its numeric keys; choose your representation accordingly.

Group filters run on explicitly submitted empty arrays, too. For example, a `Fieldset` filter can populate a default value when the request contains `'profile' => []`. An entirely omitted fieldset stays absent; if the filter leaves an empty array unchanged, its previous empty-group behavior is preserved.

Defaults propagate to existing and future fields, nested fieldsets and collections. A field's explicit filter of the **same class** overrides its default filter. Filters and validators are keyed by their concrete class; adding another instance of the same class replaces the previous instance, rather than adding a second stage.

Both indexed lists and class-keyed arrays work with `Field::setFilters()` and `Field::setDefaultFilters()`:

```php
$field->setFilters([new TrimFilter()]);
$field->setDefaultFilters([new TrimFilter()]);
```

On `Field` and form groups, `addValidator()`, `getValidator()`, `hasValidator()` and `removeValidator()` manage validators. `addValidator()` replaces an existing validator of the same concrete class. Call `setValidators()` to replace the entire set.

### Stop on the first field validation error

By default, a form validates **all of its fields**, but each field stops after its **first failing validator**. This prevents multiple messages for the same field. Existing validator rules do not need `setStop(true)`:

```php
$this->addField('first_name')
    ->addValidator(new StringValidator())
    ->addValidator(new StringLengthValidator()->setMin(1)->setMax(120));
```

To collect every validator error per field, change the form policy:

```php
$this->setStopOnFirstError(false);
```

Override the form policy for an individual field or nested group:

```php
$this->getField('password')->setStopOnFirstError(true);
$this->getFieldset('profile')->setStopOnFirstError(false);
```

The setting is inherited by existing and future fields, nested fieldsets and collection rows, unless explicitly overridden. Changing it also affects already populated collection rows. Independent field validation continues even after another field fails. An explicit validator-level `setStop(true)` **always** stops the field's remaining validators, including when the form policy is `false`; standalone `wilycode/validator` behavior is unchanged. Built-in required/type/option checks can still fail before custom validators.

**Migration from v0.1.x:** Forms that relied on collecting every error from multiple validators attached to the same field should explicitly call `$this->setStopOnFirstError(false)`. This is a behavior change in v0.2.0.

### Notes about mutable rules

When nested collection rows are created, the library copies the field, filter and validator instances so one row's validation state does not interfere with another. A `FieldCompareValidator` bound to a **fieldset or collection** is rebound to the corresponding cloned fieldset or individual collection row; see the examples below. If you need the same validator class multiple times on a single field, wrap or compose validators explicitly—only one instance of each concrete class is stored.

### Combining SlugFilter and a validator

The ready-made `SlugFilter` transforms a title into a URL-friendly ASCII slug,
while the published `RegexValidator` verifies that the resulting value has the expected format.
Both are in the optional `wilycode/filters` and `wilycode/validators` packages.

```php
use WilyCode\Filters\SlugFilter;
use WilyCode\Validators\RegexValidator;

$this->addField('slug')
    ->addFilter(new SlugFilter())
    ->addValidator(new RegexValidator('/\A[a-z0-9]+(?:-+[a-z0-9]+)*\z/'));
```

`Café crème` becomes `cafe-creme`; `my--article` is valid. An input
containing no usable letters or digits may normalize to an empty string,
which a required field or the regex validator rejects. Uniqueness must be checked
separately by the application or database.

## Inheriting Add/Edit forms

The same field tree can serve add and edit use cases without duplicating its configuration:

```php
use WilyCode\Filters\TrimFilter;
use WilyCode\Form\Form;
use WilyCode\Validators\StringLengthValidator;

class AddArticleForm extends Form
{
    protected function init(): void
    {
        $this->addDefaultFieldFilter(new TrimFilter());
        $this->addField('title')
            ->addValidator(new StringLengthValidator()->setMax(100));
        $this->addField('password');
        $this->addField('slug');
    }
}

class EditArticleForm extends AddArticleForm
{
    protected function init(): void
    {
        parent::init();

        // Do not trim a password, but continue trimming other fields.
        $this->getField('password')->removeDefaultFilter(TrimFilter::class);

        // Replace one validator with an instance of the same class.
        $this->getField('title')->addValidator(
            new StringLengthValidator()->setMax(200),
        );
    }
}
```

To disable an inherited default throughout a form, fieldset or collection, call `removeDefaultFieldFilter(TrimFilter::class)`. Local child-group defaults and explicitly added field filters are preserved. Removal also applies to fields added **later**. To reactivate a filter locally, call `addDefaultFieldFilter()` (group) or `addDefaultFilter()` (field).

Use `removeValidator(ValidatorClass::class)` or `removeFilter(FilterClass::class)` to remove explicitly configured rules. A removed or unknown rule raises `RuntimeException` in the remove/get methods.

## Nested fieldsets and collections

Use `addFieldset()` for a named object and `addCollection()` for repeated rows. Each collection row receives independent copies of fields and validators.

```php
use WilyCode\Form\Form;
use WilyCode\Form\Validators\FieldCompareValidator;

final class PasswordsForm extends Form
{
    protected function init(): void
    {
        $rows = $this->addCollection('users');
        $credentials = $rows->addFieldset('credentials');
        $credentials->addField('password');
        $credentials->addField('confirm')->addValidator(
            new FieldCompareValidator($credentials, 'password', 'Password'),
        );
    }
}

$form = new PasswordsForm();
$form->setData(['users' => [
    ['credentials' => ['password' => 'first', 'confirm' => 'first']],
    ['credentials' => ['password' => 'second', 'confirm' => 'second']],
]]);

assert($form->isValid());
```

For fields directly inside a collection, construct the validator with the collection context:

```php
$rows = $this->addCollection('users');
$rows->addField('password');
$rows->addField('confirm')->addValidator(
    new FieldCompareValidator($rows, 'password', 'Password'),
);
```

For row-local comparisons, pass the containing group to `FieldCompareValidator`: a `Fieldset` for the nested example above, or the `Collection` itself for fields stored directly in each row. The comparison context is rebound to the concrete row during cloning. Required multiple-value fields and required collections reject empty arrays. A fieldset/collection receiving a scalar instead of a nested array produces a validation error instead of silently ignoring it.

Individual fields support `multiple()`, `single()`, `options()`, `allowCustomValues()` and `disallowCustomValues()`. A `multiple()` field expects an array of submitted values: a missing or empty selection is normalized to `[]`, while a non-empty scalar input fails validation with `validation.array.invalid_type`. A required multiple-value field also rejects arrays containing only empty choices (such as `['']` or `[null]`), but accepts `['0']` or `[0]`. Multiple-value fields expect **flat arrays**: nested arrays (including `[[]]`) are invalid for both required and optional fields and produce `validation.array.invalid_type`. This prevents malformed request data from silently becoming a valid selection.

For list-based numeric options (such as `[1, 2]`), HTML submissions provide string values. `getSelectedOptions()` and `getCustomValues()` use the same non-strict option matching as the field's default validation, so `'1'` is recognized as a configured option rather than a custom value. Explicitly opting into a strict `InOptionsValidator` remains possible.

Required fieldsets and collections must contain at least one meaningful value, including within nested groups. A collection with only blank optional fields in its rows is still empty; a fieldset containing only empty optional fields is considered empty. Optional fieldsets can remain empty, **even if they contain required fields**: an omitted group, an empty array, and a submitted group whose declared child values are only blank **after field filters** are treated as absent. Unknown submitted keys do not make an optional group non-empty. Once the group contains a meaningful declared value, the required children are validated normally. Malformed nested fieldset or collection structures still produce type errors instead of being silently ignored. This also includes scalar submissions to `multiple()` fields inside otherwise empty optional groups: normalization (for example, trimming whitespace) must not hide a malformed input type. Explicit **group-level validators** still run even for empty optional groups; this intentionally differs from the field-level required check. When building a form, **fields, fieldsets and collections must not share the same name within a group**. The library throws `InvalidArgumentException` for cross-kind collisions to prevent ambiguous data and error paths. Replacing an element with another of the same kind remains supported.

Form-specific validators in `WilyCode\Form\Validators` are `InOptionsValidator`, `ArrayCustomValuesValidator` and `FieldCompareValidator`; generic rules belong in `wilycode/validators`.

## Replacing field definitions

`setFields()`, `setFieldsets()` and `setCollections()` accept either an associative array or a plain list of matching objects. Entries are always indexed by each object's `getName()`, **not** by the input array key:

```php
$this->setFields([
    new Field('title'),
    new Field('slug'),
]);

$this->setFieldsets([new Fieldset('profile')]);
$this->setCollections([new Collection('items')]);
```

After calling `setFields()`, `$this->getField('title')` works and `getData()` returns a `title` key. Supplying two different objects of the same kind with the same name in a single `set*()` call throws `InvalidArgumentException` rather than silently replacing one. To **intentionally replace** an existing definition, use `add(new Field('title'))` or another corresponding `add...()` operation with a matching name. Different kinds of elements cannot share a name within the same group.

Collection-row cloning starts with fresh validator errors and no manual errors inherited from a prototype; filters and validators are separate instances so validations in different rows do not share mutable state.

## Request data precedence

`Form::setRequest()` marks a request as submitted when its HTTP method matches `Form::getMethod()`.

- For GET forms, it reads **query parameters**.
- For POST, PUT, PATCH and DELETE forms, it reads **parsed body and uploaded files**, without importing query parameters.
- Uploaded files override matching body values. Nested associative fields and corresponding numeric-indexed rows merge so uploads do not erase unrelated data in the same row. An empty uploads array replaces the matching body value.

Applications remain responsible for CSRF checks, authorization, persisting uploads and protecting against excessive input size or collection row counts. Merely using this library does not perform those security checks.

## Transparent fields

Fields can be excluded from aggregate form data while continuing to accept
submitted values and participate in validation. This is useful for CSRF tokens
and other technical fields.

```php
$form->add((new Field('_csrf'))->transparent());
$form->setData(['name' => 'Jan', '_csrf' => 'example-token']);
$form->isValid(); // Validates the transparent field too.
$form->getData(); // Includes name, but not _csrf.
$form->getDataByName('_csrf'); // Reads the submitted value.
```

The same behavior applies inside nested fieldsets and collection rows.
Transparent fields are still part of `getErrors()`, and their own
`Field::getData()` still returns the submitted value. To restore inclusion,
call `$field->transparent(false)`. `Form::getDataWithExcludes()` also omits
transparent fields because it works from `getData()`.

Use `$this->add($field)` to register custom subclasses of `Field`; the core
`addField()` convenience method accepts only a field name.

## Errors and form data

- `getData()` returns an array of the fields, fieldsets and collections currently defined in the form.
- `getDataByName('title')` fetches one **top-level** field, fieldset or collection value and throws for an unknown name.
- `getDataWithExcludes(['password'])` omits selected **top-level** names.
- `getErrors()` contains field validation errors keyed by field/fieldset/collection name.
- `getGeneralErrors()` contains form-level and nested group-level errors; nested errors can be arrays. Errors from a group validator and from a nested fieldset/collection with the same name are merged rather than silently overwritten. Where an `ErrorBag` tree is involved, its `errors` and `items` keys remain available. Direct numeric errors and indexed children are preserved even when numeric positions collide. Read errors by type/key rather than assuming every root error starts at index `0`.
- Malformed fieldset/collection data (such as a non-array value) produces `validation.array.invalid_type`; it does not also report a redundant `validation.not_empty.required` error for the same malformed value.
- `Field::getErrors()` returns an `ErrorBag`. Fieldsets and collections return nested arrays of errors.
- `addGeneralError(new Error(...))` attaches a manual error to the current data; it makes the group invalid and remains present across repeated `isValid()` calls. Calling `setData()` with a new payload clears manual errors and all previous validation results immediately; errors are recalculated when `isValid()` runs again.
- A form, field, fieldset or collection can be reused with new data. After `setData()`, `getErrors()` and `getGeneralErrors()` do not expose results from the previous payload. Inspect errors after `isValid()` for results about the current payload.

Error messages use codes such as `validation.not_empty.required`; applications can translate and render these codes with their own presentation layer.

## Optional extensions

The core library includes fields, fieldsets, collections, filtering and validation **contracts**. It does not ship image-upload fieldsets or require the ready-made `wilycode/filters` and `wilycode/validators` packages at runtime.

For an asynchronous image upload fieldset, install the separate **[`wilycode/form-image-upload`](https://github.com/wilycode/form-image-upload)** package:

```bash
composer require wilycode/form-image-upload
```

```php
use WilyCode\FormImageUpload\ImageUploadFieldset;

$this->add(new ImageUploadFieldset('avatar'));
```

This extension installs its own required filters and validators and handles only form data (`file`, `token`, `existingId`, `remove` and crop coordinates). Upload transport, file storage and authorization remain the application's responsibility.

## API reference

| Class or interface | Purpose |
| --- | --- |
| `Form` | Root form, request handling, submission state, validation and data access. |
| `Field` / `FieldInterface` | Scalar or multiple-value fields, filter/validator chains, options and field errors. |
| `Fieldset` | Nested named object with fields, fieldsets and collections. |
| `Collection` | Repeating rows with cloned fields and nested groups. |
| `AbstractFormGroup` | Shared child management, group filters, default field filters, validators and errors. |
| `FormMethod` | HTTP method enum (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`). |
| `Options` | Selected and custom options. |
| `FieldCompareValidator` | Compare two values in the same configured fieldset or form context. |

## Development

```bash
composer install
composer validate --strict
composer test
```

The project includes PHPUnit tests and a GitHub Actions matrix for PHP 8.4 and 8.5. Source and tests are tracked in Git; standard Composer `dist` archives exclude `tests/` and CI files through `.gitattributes` and `archive.exclude`.

License: MIT. See [LICENSE](LICENSE).


***

## <a name="changes"> </a>NOTABLE CHANGES

Always review the release notes before upgrading to identify potential
backward-compatibility breaks or behavioral changes.

---

### Blazy 4.0.0 — 2026-01-31

Blazy 4.x introduces architectural refinements, service decoupling, namespace
reorganization and improved forward-compatibility patterns. Several legacy
services, properties, and shorthand methods deprecated in 3.0.18 are now removed
or scheduled for removal.

#### 4.x Change Records

- See [4.x Change Records](https://www.drupal.org/node/3575429) for details.

#### Blazy 4.x Revamp, Deprecation and Removal Window

- Marked deprecated in **3.0.18** and **4.0.0**.
- Removed in **4.0.3** or **5.0.0** at the latest.
- Anything marked `@internal` or considered so may immediately apply to
  encourage smooth 4.x revamp.
- Any potential BC misses may be fixed or refined along the revamp; however
  these are not crucial since we aim for D12 maximum FC.

---

### 1. Service & Infrastructure Deprecations

#### Core Service Separation

- `@blazy` and `@blazy.manager.base` are decoupled from `@blazy.base`.

- The `@blazy` service now contains
  **core infrastructural services and shared logic** for the Blazy ecosystem.
  - Static methods were deprecated and moved to `\Drupal\blazy\BlazyApi`.
  - Its core container is accessible via `$core` property.

---

#### `@blazy.base` Deprecation

- If extending `@blazy.base`, use `@blazy.manager.base` instead.

Methods were moved into and aliased via:

- `BlazyInterface`
- `ContextInterface`
- `WithInterface`

This avoids inheritance pitfalls and circular references (notably relevant to
Drupal 11 hook argument handling).

Classes are now `final` to enforce architectural intent.

---

#### `@blazy.config` Deprecation

- If calling `@blazy.config`, use `@blazy` and `@blazy.with` instead.

---

#### Duplicate Method Removal

Within `BlazyManagerBaseInterface`:

Prefer:

```php
$this->core->method();
$this->context->method();
$this->with->method();
```

Instead of legacy shorthand:

```php
$this->method();
```

Non-essential duplicate methods (including BlazyBase and BlazyBaseInterface kept
only for 4.x BC) are deprecated and will be removed in favor of:

```php
::core()
::context()
::with()
```

### 2. Media Component Service Deprecations
Media component services were deprecated.

Use the renderer-based services instead:

| 3.x Service        | 4.x Replacement          |
|--------------------|--------------------------|
| `@blazy.file`      | `@blazy.file_renderer`   |
| `@blazy.svg`       | `@blazy.svg_renderer`    |
| `@blazy.media`     | `@blazy.media_renderer`  |
| `@blazy.oembed`    | `@blazy.oembed_renderer` |
| `@blazy.entity`    | `@blazy.entity_renderer` |

Public access in 4.x is available through:
```
@blazy.media_render
```
This acts as the coordinating layer.
Direct access to deprecated services in 4.x may result in runtime errors.

The `@blazy.media_render` is loaded as required such as in entity or media
related formatters, Views styles and fields. This is never a dependency for
`BlazyManager` which operates more on translating generic data or inputs rather
than dealing with any renderer directly.

### 3. Namespace & Property Deprecations

#### Namespace Reorganization
To improve structure, the following namespaces moved to `src/Infra`:

| 3.x Namespace            | 4.x Namespace              |
|--------------------------|----------------------------|
| `Drupal\blazy\Asset`     | `Drupal\blazy\Infra`       |
| `Drupal\blazy\Config`    | `Drupal\blazy\Infra\Config`|
| `Drupal\blazy\Field`     | `Drupal\blazy\Infra\Field` |
| `Drupal\blazy\Form`      | `Drupal\blazy\Infra\Form`  |
| `Drupal\blazy\Views`     | `Drupal\blazy\Infra\Views` |

#### Deprecated BlazyBase Properties → $core Accessors

| 3.x Property            | 4.x Equivalent                    |
|-------------------------|-----------------------------------|
| `$root`                 | `$core->root()`                   |
| `$cache`                | `$core->cache()`                  |
| `$configFactory`        | `$core->configFactory()`          |
| `$entityRepository`     | `$core->entityRepository()`       |
| `$entityTypeManager`    | `$core->entityTypeManager()`      |
| `$languageManager`      | `$core->languageManager()`        |
| `$moduleHandler`        | `$core->moduleHandler()`          |
| `$renderer`             | `$core->renderer()`               |
| `$libraries`            | `$with->libraries()`              |

### 4. Settings Handling Change
The settings array is now cloned into:

`BlazySettings::config`

This allows safer and more convenient customization and operations without
mutating the original settings array directly.

See:
[blazy.api.php](https://git.drupalcode.org/project/blazy/blob/4.0.x/blazy.api.php)

---

### Blazy 3.0.0 — 2023-09-18
Initial 3.x stable architecture up to Drupal 11.

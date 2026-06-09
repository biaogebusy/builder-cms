# AI Module CSS Utility Library

The AI module provides a shared CSS utility library for consistent styling across AI-related admin interfaces. This library is designed to be used by both the core AI module and contrib modules in the AI ecosystem.

**Related issue:** [#3567389: Create shared UI component library for AI admin interfaces](https://www.drupal.org/project/ai/issues/3567389)

## Design Principles

### 1. Drupal Core CSS Custom Properties

All utilities leverage [Drupal core CSS custom properties](https://api.drupal.org/api/drupal/core%21themes%21claro%21css%21base%21variables.css/11.x) for consistency across admin themes. This ensures styles adapt automatically to Claro, Gin, and other admin themes that implement these variables.

### 2. Namespace Prefixing

All classes use the `ai-` prefix to prevent conflicts with Drupal core, admin themes, and other contrib modules:

```css
/* Correct */
.ai-description { }
.ai-heading-h4 { }

/* Incorrect - no prefix */
.description { }
.heading-h4 { }
```

### 3. Class-Based Selectors

Following [Drupal CSS coding standards](https://www.drupal.org/docs/develop/standards/css/css-coding-standards), selectors are class-based rather than element-based. When element qualification is needed for specificity, the class remains the primary selector:

```css
/* Preferred - class only */
.ai-description { }

/* Acceptable - element qualification for specificity */
select.ai-cell-select { }

/* Avoid - element as primary selector */
table td .ai-class { }
```

### 4. Theme Compatibility

Utilities use Gin CSS variables with fallbacks to Claro/core variables for cross-theme compatibility:

```css
.ai-icon-button {
  color: var(--gin-icon-color, var(--ai-text-color-muted));
  border-radius: var(--gin-border-m, 0.5rem);
}
```

## Available Utilities

### Typography

#### Font Size Utilities

Use these utility classes for semantic size choices in admin UI text. Exact rendered size depends on active theme variables.

| Class | Purpose |
|-------|---------|
| `.ai-font-size-s` | Small text |
| `.ai-font-size-xs` | Extra small text |
| `.ai-font-size-xxs` | Very small helper text |

See `assets/css/ai_global.css` for current variable mappings and implementation details.

#### Heading Size Overrides

Use these to visually resize headings while maintaining semantic HTML structure. For example, use an `<h2>` for document outline but style it at an `<h4>` size:

| Class | CSS Variable | Use Case |
|-------|-------------|----------|
| `.ai-heading-h1` | `--font-size-h1` | Largest heading size |
| `.ai-heading-h2` | `--font-size-h2` | Second-level heading size |
| `.ai-heading-h3` | `--font-size-h3` | Third-level heading size |
| `.ai-heading-h4` | `--font-size-h4` | Fourth-level heading size |
| `.ai-heading-h5` | `--font-size-h5` | Fifth-level heading size |
| `.ai-heading-h6` | `--font-size-h6` | Smallest heading size |

**Example:**
```php
$form['section_heading'] = [
  '#type' => 'html_tag',
  '#tag' => 'h2',
  '#value' => $this->t('Section Title'),
  '#attributes' => ['class' => ['ai-heading-h4']],
];
```

#### Text Styling

| Class | Description |
|-------|-------------|
| `.ai-text-muted` | Muted/secondary text color using `--color-text-light` |
| `.ai-description` | Semantic class for helper text combining small font size, muted color, and balanced text wrapping |

### Form Elements

#### Table Cell Styling

These classes are provided by the `ai/ai_settings_form` library.

| Class | Description |
|-------|-------------|
| `.ai-providers-cell` | Smaller font size for provider lists in capability tables |

#### Select Width Utilities

Constrain select dropdown widths for cleaner table layouts:

| Class | Max Width | Use Case |
|-------|-----------|----------|
| `.ai-select--narrow` | 150px | Compact dropdowns with short options |
| `.ai-select` | 200px | Default width for most select elements |
| `.ai-select--wide` | 300px | Dropdowns with longer option text |

**Example:**
```php
// Default width (200px)
$row['provider'] = [
  '#type' => 'select',
  '#options' => $providers,
  '#attributes' => ['class' => ['ai-select']],
];

// Wide variant for longer options (300px)
$row['model'] = [
  '#type' => 'select',
  '#options' => $models,
  '#attributes' => ['class' => ['ai-select--wide']],
];
```

### Icon Links

Use `.ai-icon-button` on an `<a>` with a `.ai-icon--*` class for accessible icon links.

```html
<a href="#options" title="Options" class="ai-icon-button ai-icon--option">
  <span class="visually-hidden">Options</span>
</a>
```

#### Container

Use this wrapper to keep icon links aligned and evenly spaced in settings table cells.
This class is provided by the `ai/ai_settings_form` library.

| Class | Description |
|-------|-------------|
| `.ai-info-cell` | Flex container for icon links with row layout and consistent spacing |

#### Link Variants

| Class | Description | Icon |
|-------|-------------|------|
| `.ai-icon-button` | Base styles for icon-only links with hover/focus/active states | - |
| `.ai-icon--provider` | Provider information icon | Plugs icon |
| `.ai-icon--model` | Model information icon | Cube icon |

**Example:**
```php
$row['info'] = [
  '#type' => 'container',
  '#attributes' => ['class' => ['ai-info-cell']],
  'provider_link' => [
    '#type' => 'link',
    '#title' => [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('@provider Provider Information', ['@provider' => $provider_name]),
      '#attributes' => ['class' => ['visually-hidden']],
    ],
    '#url' => Url::fromUri($provider_url),
    '#attributes' => [
      'class' => ['ai-icon-button', 'ai-icon--provider'],
      'target' => '_blank',
      'rel' => 'noopener noreferrer',
      'title' => $this->t('@provider Provider Information', ['@provider' => $provider_name]),
    ],
  ],
];
```

## Attaching the Library

To use these utilities in your module, attach the library to your form or render array:

```php
$form['#attached']['library'][] = 'ai/ai_global';
$form['#attached']['library'][] = 'ai/ai_settings_form';
```

The libraries are defined in `ai.libraries.yml`:

```yaml
ai_global:
  css:
    component:
      assets/css/ai_global.css: {}

ai_settings_form:
  css:
    component:
      assets/css/ai_settings_form.css: {}
```

## For Contrib Module Developers

### When to Use This Library

Use the AI CSS utility library when:

- Building admin configuration forms for AI-related functionality
- Creating capability tables or provider selection interfaces
- Displaying info links to external documentation (provider pricing, model info)
- Needing consistent typography that adapts to the active admin theme

### Adding New Utilities

When contributing new utilities to this library:

1. **Use the `ai-` prefix** for all class names
2. **Leverage Drupal CSS custom properties** where available
3. **Provide fallbacks** for theme variables (Gin -> Claro -> hardcoded)
4. **Document the utility** in this file with examples
5. **Follow Drupal CSS coding standards**

### Icon Assets

Custom icons are stored in `assets/icons/` as SVG files with `fill="currentColor"` for theme color inheritance:

- `plugs.svg` - Provider/connection icon
- `cube.svg` - Model/resource icon

## Related Documentation

- [Drupal CSS Coding Standards](https://www.drupal.org/docs/develop/standards/css/css-coding-standards)
- [Claro CSS Custom Properties](https://api.drupal.org/api/drupal/core%21themes%21claro%21css%21base%21variables.css/11.x)
- [Gin Admin Theme](https://www.drupal.org/project/gin)
- [GitHub Primer (IconButton)](https://primer.style/components/icon-button) *(Github Primer UI Library has nice UI patterns we can reference if there's not any examples in core, or Gin. Design intent only, not code)*

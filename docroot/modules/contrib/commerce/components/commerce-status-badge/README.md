# Commerce Status Badge

A Single Directory Component (SDC) for displaying status indicators with different color variants.

## Usage

### In Twig templates:

```twig
{% include 'commerce:commerce-status-badge' with {
  text: 'Locked',
  variant: 'warning'
} %}
```

### In PHP render arrays:

```php
[
  '#type' => 'component',
  '#component' => 'commerce:commerce-status-badge',
  '#props' => [
    'text' => $this->t('Completed'),
    'variant' => 'success',
  ],
]
```

## Props

- **text** (string, required): The badge text content
- **variant** (string, optional): The badge style variant. Defaults to 'neutral'
  - `warning` - Yellow/orange badge for warnings or locked states
  - `success` - Green badge for successful or completed states
  - `danger` - Red badge for errors or critical states
  - `neutral` - Gray badge for neutral or informational states

## Variants

### Warning
```twig
{% include 'commerce:commerce-status-badge' with {
  text: 'Locked',
  variant: 'warning'
} %}
```

### Success
```twig
{% include 'commerce:commerce-status-badge' with {
  text: 'Completed',
  variant: 'success'
} %}
```

### Danger
```twig
{% include 'commerce:commerce-status-badge' with {
  text: 'Failed',
  variant: 'danger'
} %}
```

### Neutral
```twig
{% include 'commerce:commerce-status-badge' with {
  text: 'Pending',
  variant: 'neutral'
} %}
```


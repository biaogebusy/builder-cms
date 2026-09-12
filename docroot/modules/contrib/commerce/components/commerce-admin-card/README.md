# Commerce Admin Card

A Single Directory Component (SDC) for displaying commerce administration content in a card format.

## Usage

### In Twig templates:

```twig
{% include 'commerce:commerce-admin-card' with {
  title: 'Shipping information'|t,
  content: order.shipping_information,
  collapsible: true,
  collapsed: false,
} %}
```

### In Twig (with embed):

```twig
{% embed 'commerce:commerce-admin-card' with {
  title: 'Billing information'|t,
  badge: edit_link,
  collapsible: true,
  collapsed: true,
} %}
  {% block card_content %}
    {{ billing_profile }}
  {% endblock %}
{% endembed %}
```

### Custom Header Example:

```twig
{% embed 'commerce:commerce-admin-card' with {
  id: 'order-actions',
  collapsible: true,
  collapsed: false,
} %}
  {% block card_header %}
    <h3 class="commerce-actions__title">
      {{ 'Order actions:'|t }}
      {% include 'commerce:commerce-status-badge' with {
        text: order_state,
        variant: 'success'
      } %}
    </h3>
  {% endblock %}
  {% block card_content %}
    {{ local_actions }}
  {% endblock %}
{% endembed %}
```

**Note:** When using a custom header with `collapsible: true`, the toggle button is automatically added to the header. You don't need to include it in your header block.

### Footer Example:

```twig
{% embed 'commerce:commerce-admin-card' with {
  title: 'Order summary'|t,
  content: order_summary,
} %}
  {% block card_footer %}
    <div class="card__footer">
      <div class="order-totals">
        <strong>{{ 'Total:'|t }}</strong> {{ order.total_price }}
      </div>
    </div>
  {% endblock %}
{% endembed %}
```

### In PHP (render array):

```php
[
  '#type' => 'component',
  '#component' => 'commerce:commerce-admin-card',
  '#props' => [
    'title' => $this->t('Billing information'),
    'badge' => $edit_link,
  ],
  '#slots' => [
    'card_content' => $content,
  ],
]
```

## Props

- **title** (string, optional): The card title text
- **title_tag** (string, optional): HTML tag to use for the title (e.g., 'h1', 'h2', 'h3', 'span'). Defaults to 'span'.
- **badge** (string, optional): The badge or link markup displayed in the header
- **content** (object, optional): The main content - can be a string or render array (alternative to using the content slot)
- **collapsible** (bool, optional): Enables the built-in, JavaScript-powered collapse/expand toggle for the card body
- **collapsed** (bool, optional): When `collapsible` is enabled, set to `true` to render the card body collapsed by default
- **id** (string, optional): Provide a unique ID for the card element (defaults to a generated `commerce-admin-card-<random>` value); the collapsible region automatically uses `<id>__content`.

## Slots

- **card_header**: Override the header content (title, badge). The toggle button is automatically rendered when `collapsible` is enabled, so you don't need to include it in your header block.
- **card_content**: The main content of the card (alternative to using the content prop)
- **card_footer**: Footer content displayed below the main content (e.g., action buttons, totals, metadata)

## Notes

- When `collapsible` is enabled the component ships with its own Drupal behavior (`commerce-admin-card.js`) to manage the toggle logic.
- By default the template generates IDs in the form `commerce-admin-card-<random>` / `__content`. Provide `id` when deterministic values are required.
- The behavior automatically wires up unique `aria-controls`/`id` attributes internally when needed, so custom IDs are optional.

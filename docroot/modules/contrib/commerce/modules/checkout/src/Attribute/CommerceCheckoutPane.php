<?php

declare(strict_types=1);

namespace Drupal\commerce_checkout\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceCheckoutPane attribute.
 *
 * Additional attribute keys for checkout panes can be defined in
 * hook_commerce_checkout_pane_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceCheckoutPane extends Plugin {

  /**
   * Constructs a CommerceCheckoutPane attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The checkout pane label.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $display_label
   *   (optional) The checkout pane display label, defaults to the label.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $admin_description
   *   The optional admin description for this pane.
   * @param string|null $default_step
   *   The ID of the default step for this pane.
   *   (optional) If missing, the pane will be disabled by default.
   * @param string|null $wrapper_element
   *   (optional) The wrapper element to use when rendering the pane's form.
   *   E.g: 'container', 'fieldset'. Defaults to 'container'.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public ?TranslatableMarkup $display_label = NULL,
    public ?TranslatableMarkup $admin_description = NULL,
    public readonly ?string $default_step = NULL,
    public readonly ?string $wrapper_element = NULL,
    public readonly ?string $deriver = NULL,
  ) {
    if (empty($this->display_label)) {
      $this->display_label = $this->label;
    }
  }

}

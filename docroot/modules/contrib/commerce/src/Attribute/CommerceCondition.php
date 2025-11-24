<?php

declare(strict_types=1);

namespace Drupal\commerce\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CommerceCondition attribute.
 *
 * Additional attribute keys for conditions can be defined in
 * hook_commerce_condition_info_alter().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CommerceCondition extends Plugin {

  /**
   * Constructs a CommerceCondition attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The condition label.
   * @param string $entity_type
   *   The condition entity type ID.
   *   This is the entity type ID of the entity passed to the plugin during
   *   evaluation. For example: 'commerce_order'.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $display_label
   *   (optional) The condition display label, defaults to the label.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) The condition description.
   *   Shown in the condition UI when enabling/disabling a condition.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $category
   *   (optional) The condition category.
   * @param string|null $parent_entity_type
   *   The parent entity type ID.
   *   This is the entity type ID of the entity that embeds the conditions.
   *   For example: 'commerce_promotion'.
   *   When specified, a condition will only be available on that entity type.
   * @param int $weight
   *   The condition weight. Used when sorting the condition list in the UI.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param array $additional
   *   (optional) An additional array of properties.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly string $entity_type,
    public ?TranslatableMarkup $display_label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public ?TranslatableMarkup $category = NULL,
    public readonly ?string $parent_entity_type = NULL,
    public readonly int $weight = 0,
    public readonly ?string $deriver = NULL,
    public readonly array $additional = [],
  ) {
    if (empty($this->display_label)) {
      $this->display_label = $this->label;
    }
    if (empty($this->category)) {
      $this->category = new TranslatableMarkup('Other');
    }
  }

}

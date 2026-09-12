<?php

namespace Drupal\entity_share_client\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an Import Processor attribute object.
 *
 * Plugin namespace: EntityShareClient\Processor.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ImportProcessor extends Plugin {

  /**
   * Constructs an ImportProcessor attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The plugin label.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The plugin description.
   * @param array $stages
   *   The stages this processor will run in, along with their default weights.
   *   This is represented as an associative array, mapping one or more of the
   *   stage identifiers to the default weight for that stage. For the available
   *   stages, see
   *   \Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager::getProcessingStages().
   * @param bool $locked
   *   (optional) If the processor should always be enabled.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
    public readonly array $stages,
    public readonly bool $locked = FALSE,
  ) {
  }

}

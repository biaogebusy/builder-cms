<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Entity Share Client import processor annotation object.
 *
 * @ingroup plugin_api
 *
 * @Annotation
 */
class ImportProcessor extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin label.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The plugin description.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

  /**
   * The stages this processor will run in, along with their default weights.
   *
   * This is represented as an associative array, mapping one or more of the
   * stage identifiers to the default weight for that stage. For the available
   * stages, see
   * \Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager::getProcessingStages().
   *
   * @var int[]
   */
  public $stages;

  /**
   * If the processor should always be enabled.
   *
   * @var bool
   */
  public $locked;

}

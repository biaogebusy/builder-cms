<?php

namespace Drupal\ai_automators\PluginInterfaces;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Optional post-check hook support for empty-state normalization.
 */
interface AiAutomatorPostCheckIfEmptyInterface {

  /**
   * Adjusts checkIfEmpty() output before final process/skip decision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param array $value
   *   The normalized value from checkIfEmpty().
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return array
   *   Returns an empty array if the value should be considered empty.
   */
  public function postCheckIfEmpty(ContentEntityInterface $entity, array $value, array $automatorConfig = []): array;

}

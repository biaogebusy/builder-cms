<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

/**
 * Jsonapi helper interface methods.
 */
interface JsonapiHelperInterface {

  /**
   * Helper function to unserialize an entity from the JSON:API response.
   *
   * @param array $data
   *   An array of data.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   An unserialize entity.
   */
  public function extractEntity(array $data);

}

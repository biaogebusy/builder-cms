<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\entity_share_client\Entity\RemoteInterface;

/**
 * Form helper interface methods.
 */
interface FormHelperInterface {

  /**
   * Prepare entities from an URI to request.
   *
   * @param array $json_data
   *   An array of data send by the JSON:API.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   The selected remote.
   * @param string $channel_id
   *   The selected channel id.
   *
   * @throws \Drupal\entity_share_client\Exception\ResourceTypeNotFoundException
   *
   * @return array
   *   The array of options for the tableselect form type element.
   */
  public function buildEntitiesOptions(array $json_data, RemoteInterface $remote, $channel_id);

}

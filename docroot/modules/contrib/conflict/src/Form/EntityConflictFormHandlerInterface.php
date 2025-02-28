<?php

namespace Drupal\conflict\Form;

use Drupal\Core\Entity\EntityInterface;

interface EntityConflictFormHandlerInterface {

  /**
   * Builds an entity conflict form.
   *
   * @param array $form
   *   The form structure to fill in.
   * @param \Drupal\Core\Entity\EntityInterface $entity_local_edited
   *   The locally edited entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity_local_edited
   *   The locally used original entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity_server
   *   The unchanged entity loaded from the storage.
   */
  public function buildConflictForm(&$form, EntityInterface $entity_local_edited, EntityInterface $entity_local_original, EntityInterface $entity_server);

}

<?php

namespace Drupal\conflict\Entity;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

interface ConflictUIResolverHandlerInterface {

  /**
   * Adds a conflict resolution to the build array.
   *
   * @param $build
   *   The render array to fill in the conflict resolution
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response.
   *
   * @return mixed
   */
  public function addConflictResolution($path, FormStateInterface $form_state, EntityInterface $entity, &$build, AjaxResponse $response);

}

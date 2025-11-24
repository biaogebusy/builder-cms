<?php

/**
 * @file
 * Contains entity_theme_engine module api.
 */

use Drupal\entity_theme_engine\Entity\EntityWidget;
use Drupal\Core\Entity\EntityInterface;

function hook_entity_widget_variables_alter(array &$variables, EntityWidget $widget, EntityInterface $entity) {
  //Do some update on $variables.
}

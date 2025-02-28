<?php

use Drupal\Core\Form\FormStateInterface;
/**
 * @file
 * Hooks and documentation related to conflict module.
 */

/**
 * @defgroup conflict Conflict API
 *
 * @{
 * Conflict module provides a way of merging concurrently edited entities.
 * @} End of "defgroup diff".
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the conflict paths before the conflict resolution has started.
 *
 * @param array $conflict_paths
 *   The conflict paths, keyed by the conflict path and having as value the
 *   entity metadata consisting of
 *   -entity_type
 *   -entity_id
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The main form state.
 */
function hook_conflict_paths_alter(array &$conflict_paths, FormStateInterface $form_state) {}

/**
 * @} End of "addtogroup hooks".
 */

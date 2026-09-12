<?php

/**
 * @file
 * Describes API functions for tour module.
 */

/**
 * @defgroup help_docs Help and documentation
 * @{
 * Documenting modules, themes, and install profiles
 *
 * @section sec_tour Tours
 * Modules can provide tours of administrative pages by creating tour config
 * files and placing them in their config/optional subdirectory. See
 * @link https://www.drupal.org/docs/8/api/tour-api/overview Tour API overview @endlink
 * for more information. The contributed
 * @}
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow modules to alter tour items before render.
 *
 * @param array $tour_tips
 *   Array of \Drupal\tour\TipPluginInterface items.
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The tour which contains the $tour_tips.
 */
function hook_tour_tips_alter(array &$tour_tips, \Drupal\Core\Entity\EntityInterface $entity) {
  foreach ($tour_tips as $tour_tip) {
    if ($tour_tip->get('id') == 'tour-code-test-1') {
      $tour_tip->set('body', 'Altered by hook_tour_tips_alter');
    }
  }
}

/**
 * Allow modules to alter tip plugin definitions.
 *
 * @param array $info
 *   The array of tip plugin definitions, keyed by plugin ID.
 *
 * @see \Drupal\tour\Annotation\Tip
 */
function hook_tour_tips_info_alter(&$info) {
  // Swap out the class used for this tip plugin.
  if (isset($info['text'])) {
    $info['class'] = 'Drupal\my_module\Plugin\tour\tip\MyCustomTipPlugin';
  }
}

/**
 * Allow modules to alter the tour build array before rendering.
 *
 * @param array $build
 *   The render array for the tour.
 * @param \Drupal\tour\TourInterface[] $entities
 *   An array of tour entities that are being rendered.
 */
function hook_tour_build_alter(array &$build, array $entities) {
  // Conditionally add a custom library to all tours:
  if (Drupal::currentUser()->hasPermission('administer tour')) {
    $build['#attached']['library'][] = 'my_module/tour_styling';
  }
}

/**
 * @} End of "addtogroup hooks".
 */

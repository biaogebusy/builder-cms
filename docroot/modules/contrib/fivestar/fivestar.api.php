<?php

/**
 * @file
 * Provides API documentation for the Fivestar module.
 */

/**
 * Hook to provide widgets for the Fivestar module.
 *
 * This hook is used by Fivestar to define the default widgets, and may be
 * use by other modules to provide additional custom widgets for Fivestar.
 *
 * @return array
 *   An associative array of widget definitions. Each element consists of
 *   a key formatted as a machine name (ie no spaces, use underscores, etc),
 *   and a value which is itself a two element array containing:
 *   - label: The human-readable name of the widget, for use in the UI.
 *   - library: The fully-qualified name of the library holding the CSS and
 *     images for the widget.
 *
 * @see fivestar_fivestar_widgets()
 */
function hook_fivestar_widgets() {
  // Let Fivestar know about my Cool and Awesome Stars.
  $widgets = [
    'awesome' => [
      'library' => 'mymodule/awesome',
      'label' => t('Awesome Stars'),
    ],
    'cool' => [
      'library' => 'mymodule/cool',
      'label' => t('Cool Stars'),
    ],
  ];

  return $widgets;
}

/**
 * Hook to alter the widgets used by Fivestar.
 *
 * This hook allows modules to alter the list of widgets used by Fivestar,
 * for example to rename or remove widgets.
 *
 * @param array $widgets
 *   An associative array of widget definitions, identical in structure
 *   to the array returned by hook_fivestar_widgets().
 *
 * @see hook_fivestar_widgets()
 * @see fivestar_widget_provider_fivestar_widgets_alter()
 */
function hook_fivestar_widgets_alter(array &$widgets) {
  // Rename 'Awesome Stars' to 'Pretty good stars'.
  $widgets['awesome']['label'] = t('Pretty good stars');

  // Remove the 'Hearts' widget.
  unset($widgets['hearts']);
}

/**
 * Hook to alter access to voting in Fivestar.
 *
 * This hook is called before every vote is cast through Fivestar. It allows
 * modules to allow or deny voting on any type of entity, such as nodes, users,
 * or comments.
 *
 * @param string $entity_type
 *   Type entity.
 * @param string $id
 *   Identifier within the type.
 * @param string $vote_type
 *   The VotingAPI tag string.
 * @param int $uid
 *   The user ID trying to cast the vote.
 *
 * @return bool|null
 *   Returns TRUE if voting is supported on this object.
 *   Returns NULL if voting is not supported on this object by this module.
 *   If needing to absolutely deny all voting on this object, regardless
 *   of permissions defined in other modules, return FALSE. Note if all
 *   modules return NULL, stating no preference, then access will be denied.
 *
 * @see fivestar_validate_target()
 * @see fivestar_fivestar_access()
 */
function hook_fivestar_access($entity_type, $id, $vote_type, $uid) {
  if ($uid == 1) {
    // We are never going to allow the admin user to cast a Fivestar vote.
    return FALSE;
  }
}

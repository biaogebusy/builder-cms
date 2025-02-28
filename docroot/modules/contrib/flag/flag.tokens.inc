<?php

/**
 * @file
 * Flag module tokens support.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function flag_token_info() {
  $types = [];
  $tokens = [];

  // Flag tokens.
  $types['flag'] = [
    'name' => t('Flags'),
    'description' => t('Tokens related to flag data.'),
    'needs-data' => 'flag',
  ];
  $tokens['flag']['name'] = [
    'name' => t('Flag name'),
    'description' => t('The flag machine-readable name.'),
  ];
  $tokens['flag']['title'] = [
    'name' => t('Flag title'),
    'description' => t('The human-readable flag title.'),
  ];

  // Flagging tokens.
  //
  // Attached fields are exposed as tokens via some contrib module, but we
  // need to expose other fields ourselves. Currently, 'date' is the only such
  // field we expose.
  $types['flagging'] = [
    'name' => t('Flaggings'),
    'description' => t('Tokens related to flaggings.'),
    'needs-data' => 'flagging',
  ];
  $tokens['flagging']['date'] = [
    'name' => t('Flagging date'),
    'description' => t('The date an item was flagged.'),
    'type' => 'date',
  ];

  // Flag action tokens.
  $types['flag-action'] = [
    'name' => t('Flag actions'),
    'description' => t('Tokens available in response to a flag action being executed by a user.'),
    'needs-data' => 'flag-action',
  ];
  $tokens['flag-action']['action'] = [
    'name' => t('Flag action'),
    'description' => t('The flagging action taking place, either "flag" or "unflag".'),
  ];
  $tokens['flag-action']['entity-url'] = [
    'name' => t('Flag entity URL'),
    'description' => t('The URL of the entity being flagged.'),
  ];
  $tokens['flag-action']['entity-title'] = [
    'name' => t('Flag entity title'),
    'description' => t('The title of the entity being flagged.'),
  ];
  $tokens['flag-action']['entity-type'] = [
    'name' => t('Flag entity type'),
    'description' => t('The type of entity being flagged, such as <em>node</em> or <em>comment</em>.'),
  ];
  $tokens['flag-action']['entity-id'] = [
    'name' => t('Flag entity ID'),
    'description' => t('The ID of entity being flagged, such as a nid or cid.'),
  ];
  $tokens['flag-action']['count'] = [
    'name' => t('Flag count'),
    'description' => t('The current count total for this flag.'),
  ];

  // Add tokens for the flag count available at the node/comment/user level.
  /** \Drupal\flag\FlagInterface[] $flags */
  $flags = \Drupal::service('flag')->getAllFlags();
  foreach ($flags as $id => $flag) {
    $flag_entity_type_id = $flag->getFlaggableEntityTypeId();
    $tokens[$flag_entity_type_id]['flag-' . str_replace('_', '-', $id) . '-count'] = [
      'name' => t('@flag flag count', ['@flag' => $flag->label()]),
      'description' => t('Total flag count for flag @flag', ['@flag' => $flag->label()]),
    ];
    $tokens[$flag_entity_type_id]['flag-' . str_replace('_', '-', $id) . '-link'] = [
      'name' => t('@flag flag link', ['@flag' => $flag->label()]),
      'description' => t('Flag/unflag link for @flag', ['@flag' => $flag->label()]),
    ];
  }

  return [
    'types' => $types,
    'tokens' => $tokens,
  ];
}

/**
 * Implements hook_tokens().
 */
function flag_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  /*
  $replacements = array();
  $sanitize = !empty($options['sanitize']);
  $langcode = isset($options['language']) ? $options['language']->language : NULL;

  if ($type == 'flag' && !empty($data['flag'])) {
    $flag = $data['flag'];
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'name':
          $replacements[$original] = $sanitize ? check_plain($flag->name) : $flag->name;
          break;

        case 'title':
          $replacements[$original] = $sanitize ? check_plain($flag->get_title()) : $flag->get_title();
          break;
      }
    }
  }
  elseif ($type == 'flagging' && !empty($data['flagging'])) {
    $flagging = $data['flagging'];
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'date':
          $replacements[$original] = format_date($flagging->timestamp, 'medium', '', NULL, $langcode);
          break;
      }
    }
    if ($date_tokens = token_find_with_prefix($tokens, 'date')) {
      $replacements += token_generate('date', $date_tokens, array('date' => $flagging->timestamp), $options);
    }
  }
  elseif ($type == 'flag-action' && !empty($data['flag-action'])) {
    $action = $data['flag-action'];
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'action':
          $replacements[$original] = $action->action;
          break;

        case 'entity-url':
          $replacements[$original] = $sanitize ? check_url($action->entity_url) : $action->entity_url;
          break;

        case 'entity-title':
          $replacements[$original] = $sanitize ? check_plain($action->entity_title) : $action->entity_title;
          break;

        case 'entity-type':
          $replacements[$original] = $action->entity_type;
          break;

        case 'entity-id':
          $replacements[$original] = $action->entity_id;
          break;

        case 'count':
          $replacements[$original] = $action->count;
          break;
      }
    }
  }

  if (isset($data[$type]) && in_array($type, \Drupal::service('flag')->fetchDefinition())) {
    $flags = \Drupal::service('flag')->getAllFlags($type);
    $object = $data[$type];
    foreach ($flags as $flag) {
      foreach ($tokens as $name => $original) {
        $flag_count_token = 'flag-' . str_replace('_', '-', $flag->name) . '-count';
        $flag_link_token = 'flag-' . str_replace('_', '-', $flag->name) . '-link';
        if ($name == $flag_count_token) {
          $replacements[$original] = $flag->get_count($flag->get_entity_id($object));
        }
        elseif ($name == $flag_link_token) {
          $replacements[$original] = flag_create_link($flag->name, $flag->get_entity_id($object));
        }
      }
    }
  }
  return $replacements;
  */
}

/**
 * Returns HTML for a tokens browser.
 *
 * @param array $variables
 *   An associative array containing:
 *   - types: An array naming the types of tokens to show.
 *   - global_types: Whether to show global tokens.
 */
function theme_flag_tokens_browser(array $variables) {
  $types = $variables['types'];
  $global_types = $variables['global_types'];

  if (\Drupal::moduleHandler()->moduleExists('token')) {
    return theme('token_tree', ['token_types' => $types, 'global_types' => $global_types]);
  }
  else {
    return '<p><em>' . t("Note: You don't have the <a href='@token-url'>Token</a> module installed, so the list of available tokens isn't shown here. You don't have to install <a href='@token-url'>Token</a> to be able to use tokens, but if you have it installed, and enabled, you'll be able to enjoy an interactive tokens browser.", ['@token-url' => 'http://drupal.org/project/token']) . '</em></p>';
  }
}

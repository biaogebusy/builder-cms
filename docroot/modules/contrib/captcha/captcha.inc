<?php

/**
 * @file
 * General CAPTCHA functionality and helper functions.
 */

use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Element;
use Drupal\captcha\Constants\CaptchaConstants;
use Drupal\captcha\Entity\CaptchaPoint;

/**
 * Helper function for adding/updating a CAPTCHA point.
 *
 * @param string $form_id
 *   the form ID to configure.
 * @param string $captcha_type
 *   The setting for the given form_id, can be:
 *   - 'default' to use the default challenge type
 *   - NULL to remove the entry for the CAPTCHA type
 *   - something of the form "image_captcha/Image"
 *   - an object with attributes $captcha_type->module
 *   and $captcha_type->captcha_type.
 */
function captcha_set_form_id_setting($form_id, $captcha_type) {
  /** @var Drupal\captcha\Entity\CaptchaPoint $captcha_point */
  $captcha_point = CaptchaPoint::load($form_id);

  if ($captcha_point) {
    $captcha_point->setCaptchaType($captcha_type);
  }
  else {
    $captcha_point = new CaptchaPoint([
      'formId' => $form_id,
      'captchaType' => $captcha_type,
      // @see https://www.drupal.org/project/drupal/issues/3302838
      // On config pre-save drupal tries to query same uuid, and deprecation
      // is generated. So we prefill the uuid for now.
      'uuid' => \Drupal::service('uuid')->generate(),
    ], 'captcha_point');
  }
  $captcha_point->enable();

  $captcha_point->save();
}

/**
 * Get the CAPTCHA setting for a given form_id.
 *
 * @param string $form_id
 *   The form_id to query for.
 * @param bool $symbolic
 *   Flag to return as (symbolic) strings instead of object.
 *
 * @return null|CaptchaPoint
 *   NULL if no setting is known
 *   captcha point object with fields 'module' and 'captcha_type'.
 *   If argument $symbolic is true, returns 'default' or in the
 *   form 'captcha/Math'.
 */
function captcha_get_form_id_setting($form_id, $symbolic = FALSE) {
  /** @var \Drupal\captcha\Entity\CaptchaPoint $captchaPoint */
  $captcha_point = CaptchaPoint::load($form_id);

  if ($symbolic) {
    $captcha_point = $captcha_point->getCaptchaType();
  }

  return $captcha_point;
}

/**
 * Helper function for generating a new CAPTCHA session.
 *
 * @param string $form_id
 *   The form_id of the form to add a CAPTCHA to.
 * @param int $status
 *   The initial status of the CAPTCHA session.
 *
 * @return string
 *   The session ID of the new CAPTCHA session.
 */
function _captcha_generate_captcha_session($form_id = NULL, $status = CaptchaConstants::CAPTCHA_STATUS_UNSOLVED) {
  $user = \Drupal::currentUser();

  // Initialize solution with random data.
  $solution = hash('sha256', mt_rand());

  // Insert an entry and thankfully receive the value
  // of the autoincrement field 'csid'.
  $captcha_sid = \Drupal::database()->insert('captcha_sessions')
    ->fields([
      'uid' => $user->id(),
      'sid' => session_id(),
      'ip_address' => \Drupal::request()->getClientIp(),
      'timestamp' => \Drupal::time()->getRequestTime(),
      'form_id' => $form_id,
      'solution' => $solution,
      'status' => $status,
      'attempts' => 0,
    ])
    ->execute();
  return $captcha_sid;
}

/**
 * Helper function for updating the solution in the CAPTCHA session table.
 *
 * @param string $captcha_sid
 *   The CAPTCHA session ID to update.
 * @param string $solution
 *   The new solution to associate with the given CAPTCHA session.
 */
function _captcha_update_captcha_session($captcha_sid, $solution) {
  \Drupal::database()->update('captcha_sessions')
    ->condition('csid', $captcha_sid)
    ->fields([
      'timestamp' => \Drupal::time()->getRequestTime(),
      'solution' => $solution,
    ])
    ->execute();
}

/**
 * Helper function for checking if CAPTCHA is required for user.
 *
 * Based on the CAPTCHA persistence setting, the CAPTCHA session
 * ID and user session info.
 */
function _captcha_required_for_user($captcha_sid, $form_id) {
  // Get the CAPTCHA persistence setting.
  $captcha_persistence = \Drupal::config('captcha.settings')
    ->get('persistence');

  // First check: should we always add a CAPTCHA?
  if ($captcha_persistence == CaptchaConstants::CAPTCHA_PERSISTENCE_SHOW_ALWAYS) {
    return TRUE;
  }

  // Get the status of the current CAPTCHA session.
  $captcha_session_status = \Drupal::database()
    ->select('captcha_sessions', 'cs')
    ->fields('cs', ['status'])
    ->condition('csid', $captcha_sid)
    ->execute()
    ->fetchField();

  // Second check: if the current session is already
  // solved: omit further CAPTCHAs.
  if ($captcha_session_status == CaptchaConstants::CAPTCHA_STATUS_SOLVED) {
    return FALSE;
  }

  // Third check: look at the persistence level
  // (per form instance, per form or per user).
  if ($captcha_persistence == CaptchaConstants::CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE) {
    return TRUE;
  }
  else {
    $captcha_success_form_ids = isset($_SESSION['captcha_success_form_ids']) ? (array) ($_SESSION['captcha_success_form_ids']) : [];
    switch ($captcha_persistence) {
      case CaptchaConstants::CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL:
        return (count($captcha_success_form_ids) == 0);

      case CaptchaConstants::CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_TYPE:
        return !isset($captcha_success_form_ids[$form_id]);
    }
  }

  // We should never get to this point, but to be sure, we return TRUE.
  return TRUE;
}

/**
 * Get the CAPTCHA title.
 *
 * @return string
 *   CAPTCHA title.
 */
function _captcha_get_title() {
  $title = \Drupal::config('captcha.settings')->get('title');
  if ($title === NULL) {
    return '';
  }
  return Xss::filter($title);
}

/**
 * Get the CAPTCHA description.
 *
 * @return string
 *   CAPTCHA description.
 */
function _captcha_get_description() {
  $description = \Drupal::config('captcha.settings')->get('description');
  if ($description === NULL) {
    return '';
  }
  return Xss::filter($description);
}

/**
 * Gets the error message for when a user enters an incorrect CAPTCHA answer.
 *
 * @return string
 *   Error message.
 */
function _captcha_get_error_message() {
  $error_message = \Drupal::config('captcha.settings')->get('wrong_captcha_response_message');
  if ($error_message) {
    return Xss::filter($error_message);
  }
  return t('The answer you entered for the CAPTCHA was not correct.');
}

/**
 * Parse or interpret the given captcha_type.
 *
 * @param string $captcha_type
 *   representation of the CAPTCHA type,
 *   e.g. 'default', 'captcha/Math', 'image_captcha/Image'.
 *
 * @return array
 *   list($captcha_module, $captcha_type).
 */
function _captcha_parse_captcha_type($captcha_type) {
  if ($captcha_type == CaptchaConstants::CAPTCHA_TYPE_DEFAULT) {
    $captcha_type = \Drupal::config('captcha.settings')
      ->get('default_challenge', CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE);
  }
  return explode('/', $captcha_type);
}

/**
 * Helper function to get placement information for a given form_id.
 */
function _captcha_get_captcha_placement($form_id, $form) {
  // Get CAPTCHA placement map from cache. Two levels of cache:
  // static variable in this function and storage in the variables table.
  static $placement_map = NULL;

  $write_cache = FALSE;

  // Try first level cache.
  if ($placement_map === NULL) {
    // If first level cache missed: try second level cache.
    if ($cache = \Drupal::cache()->get('captcha_placement_map_cache')) {
      $placement_map = $cache->data;
    }
    else {
      // If second level cache missed: initialize the placement map
      // and let other modules hook into this with the
      // hook_captcha_placement_map hook.
      // By default however, probably all Drupal core forms
      // are already correctly handled with the best effort guess
      // based on the 'actions' element (see below).
      $placement_map = \Drupal::moduleHandler()
        ->invokeAll('captcha_placement_map');
      $write_cache = TRUE;
    }
  }

  // Query the placement map.
  if (array_key_exists($form_id, $placement_map) && $placement_map[$form_id] !== NULL) {
    $placement = $placement_map[$form_id];
  }
  // If no placement info is available in placement map:
  // make a best effort guess.
  else {
    // If there is an "actions" button group, a good placement
    // is just before that.
    if (isset($form['actions']) && isset($form['actions']['#type']) && $form['actions']['#type'] === 'actions') {
      $placement = [
        'path' => [],
        'key' => 'actions',
        // #type 'actions' defaults to 100.
        'weight' => (isset($form['actions']['#weight']) ? $form['actions']['#weight'] - 1 : 99),
      ];
    }
    else {
      // Search the form for buttons and guess placement from it.
      $buttons = _captcha_search_buttons($form);
      if (count($buttons)) {
        // Pick first button.
        // @todo make this more sofisticated? Use cases needed.
        $placement = (isset($buttons[count($buttons) - 1])) ? $buttons[count($buttons) - 1] : $buttons[0];
      }
      else {
        // Use NULL when no buttons were found.
        $placement = NULL;
      }
    }

    // Store calculated placement in cache.
    $placement_map[$form_id] = $placement;
    $write_cache = TRUE;
  }

  if ($write_cache) {
    \Drupal::cache()->set('captcha_placement_map_cache', $placement_map);
  }
  return $placement;
}

/**
 * Helper function for searching the buttons in a form.
 *
 * @param array $form
 *   The form to search button elements in.
 *
 * @return array
 *   Array of paths to the buttons.
 *   A path is an array of keys leading to the button, the last
 *   item in the path is the weight of the button element
 *   (or NULL if undefined).
 */
function _captcha_search_buttons(array $form) {
  $buttons = [];

  foreach (Element::children($form, FALSE) as $key) {
    // Look for submit or button type elements.
    if (isset($form[$key]['#type']) && ($form[$key]['#type'] == 'submit' || $form[$key]['#type'] == 'button')) {
      $weight = $form[$key]['#weight'] ?? NULL;
      $buttons[] = [
        'path' => [],
        'key' => $key,
        'weight' => $weight,
      ];
    }
    // Process children recursively.
    $children_buttons = _captcha_search_buttons($form[$key]);
    foreach ($children_buttons as $b) {
      $b['path'] = array_merge([$key], $b['path']);
      $buttons[] = $b;
    }
  }

  return $buttons;
}

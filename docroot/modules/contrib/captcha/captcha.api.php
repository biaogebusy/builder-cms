<?php

/**
 * @file
 * Hooks for the captcha module.
 */

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * A hook_captcha_captcha implementation example.
 *
 * This documentation is for developers that want to implement their own
 * challenge type and integrate it with the base CAPTCHA module.
 *
 * === Required: hook_captcha($op, $captcha_type='') ===
 *
 * The hook_captcha() hook is the only required function if you want to
 * integrate with the base CAPTCHA module.
 *
 * Functionality depends on the first argument $op:
 *   - 'list': you should return an array of possible challenge types that
 *    your module implements.
 *   - 'generate': generate a challenge.
 *
 * You should return an array that offers form elements and the solution
 * of your challenge, defined by the second argument $captcha_type.
 *
 * The returned array $captcha should have the following items:
 *   - $captcha['solution']: this is the solution of your challenge
 *   - $captcha['form']: an array of the form elements you want to add to the
 *     form.
 *   - $captcha['cacheable']: (optional) boolean indicating whether the captcha
 *     type is compatible with the form being cached. Defaults to FALSE. Note
 *     that if this TRUE, the 'captcha_validate' key must be set to a callback
 *     that ignores the solution.
 *   - $captcha['captcha_validate']: (optional) The name of a function to call
 *     to compare the solution with the given response. Defaults to
 *     captcha_validate_strict_equality().
 *
 * There should be a key 'captcha_response' in this array, which points to
 * the form element where the user enters the answer.
 *
 * An optional additional argument $captcha_sid with the captcha session ID is
 * available for more advanced challenges (e.g. the image CAPTCHA uses this
 * argument, see image_captcha_captcha()) and it is used for every session.
 *
 * Let's give a simple example to make this more clear.
 *
 * We create the challenge 'Foo CAPTCHA', which requires the user to
 * enter "foo" in a textfield.
 *
 * @param string $op
 *   List or generate captcha.
 * @param string $captcha_type
 *   Captcha type.
 * @param null|string $captcha_sid
 *   The $captcha_sid is a unique identifier for an instance of the captcha.
 *   State information on each captcha session is stored in captcha_sessions,
 *   such as is solved status.
 */
function hook_captcha_captcha($op, $captcha_type = '', $captcha_sid = NULL) {
  switch ($op) {
    case 'list':
      return ['Foo CAPTCHA'];

    case 'generate':
      if ($captcha_type == 'Foo CAPTCHA') {
        $captcha = [];
        $captcha['solution'] = 'foo';
        $captcha['form']['captcha_response'] = [
          '#type' => 'textfield',
          '#title' => t('Enter "foo"'),
          '#required' => TRUE,
        ];
        // The CAPTCHA module provides an option for case sensitive and case
        // insensitive validation of the responses. If this is not sufficient,
        // you can provide your own validation function with the
        // 'captcha_validate' field, illustrated by the following example:
        $captcha['captcha_validate'] = 'hook_captcha_custom_validation';
        return $captcha;
      }
      break;
  }
}

/**
 * Allow modules to alter a CAPTCHA.
 *
 * @param array $captcha
 *   The array returned by hook_captcha().
 * @param array $info
 *   Array of information about the CAPTCHA.
 */
function hook_captcha_alter(&$captcha, $info) {
  if ($info['module'] == 'mymodule') {
    $captcha['form']['captcha_response']['#description'] = t('New description.');
  }
}

/**
 * Implements hook_help().
 *
 * You should of course implement a function foo_captcha_settings_form() which
 * returns the form of your configuration page.
 * === Optional: hook_help($section) ===
 * To offer a description/explanation of your challenge, you can use the
 * normal hook_help() system.
 * For our simple foo CAPTCHA module this would mean:
 */
function hook_captcha_help($route_name, RouteMatchInterface $route_match) {
  switch ($route_name) {
    case 'foo_captcha.settings':
      return '<p>' . t('This is a very simple challenge, which requires users to
      enter "foo" in a textfield.') . '</p>';
  }
}

/**
 * A hook_captcha_custom_advance_validation implementation example.
 *
 * Custom CAPTCHA validation function. Previous example shows the basic usage
 * for custom validation with only a $solution and $response argument, which
 * should be sufficient for most CAPTCHA modules. More advanced CAPTCHA modules
 * can also use extra provided arguments $element and $form_state.
 *
 * @param string $solution
 *   The solution for the challenge as reported by hook_captcha('generate',...).
 * @param string $response
 *   The answer given by the user.
 *
 * @return true
 *   on success and FALSE on failure.
 */
function hook_captcha_custom_validation($solution, $response) {
  return $response == "foo" || $response == "bar";
}

/**
 * A hook_captcha_custom_advance_validation implementation example.
 *
 * Custom Advance CAPTCHA validation function. These extra arguments are the
 * $element and $form_state arguments of the validation function of the #captcha
 * element. See captcha_validate() in captcha.module for more info about this.
 *
 * @param string $solution
 *   The solution for the challenge as reported by hook_captcha('generate',...).
 * @param string $response
 *   The answer given by the user.
 * @param array $element
 *   The element argument.
 * @param array $form_state
 *   The form_state argument.
 *
 * @return true
 *   on success and FALSE on failure.
 */
function hook_captcha_custom_advance_validation($solution, $response, array $element, array $form_state) {
  return $form_state['foo']['#bar'] = 'baz';
}

/**
 * A hook_captcha_placement_map implementation example.
 *
 * === Hook into CAPTCHA placement ===
 * The CAPTCHA module attempts to place the CAPTCHA element in an appropriate
 * spot at the bottom of the targeted form, but this automatic detection may be
 * insufficient for complex forms.
 * The hook_captcha_placement_map hook allows to define the placement of the
 * CAPTCHA element as desired. The hook should return an array, mapping form IDs
 * to placement arrays, which are associative arrays with the following fields:
 * 'path': path (array of path items) of the form's container element in which
 * the CAPTCHA element should be inserted.
 * 'key': the key of the element before which the CAPTCHA element
 * should be inserted. If the field 'key' is undefined or NULL, the CAPTCHA
 * will just be appended in the container.
 * 'weight': if 'key' is not NULL: should be the weight of the element defined
 * by 'key'. If 'key' is NULL and weight is not NULL/unset: set the weight
 * property of the CAPTCHA element to this value.
 * For example:
 * This will place the CAPTCHA element
 * in the 'my_fancy_form' form inside the container $form['items']['buttons'],
 * just before the element $form['items']['buttons']['sacebutton'].
 * in the 'another_form' form at the toplevel of the form, with a weight 34.
 */
function hook_captcha_placement_map() {
  return [
    'my_fancy_form' => [
      'path' => ['items', 'buttons'],
      'key' => 'savebutton',
    ],
    'another_form' => [
      'path' => [],
      'weight' => 34,
    ],
  ];
}

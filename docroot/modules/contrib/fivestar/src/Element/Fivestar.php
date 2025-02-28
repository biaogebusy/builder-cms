<?php

namespace Drupal\fivestar\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Template\Attribute;

/**
 * Provides a fivestar form element.
 *
 * @FormElement("fivestar")
 */
class Fivestar extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);

    return [
      '#input' => TRUE,
      '#stars' => 5,
      '#allow_clear' => FALSE,
      '#allow_revote' => FALSE,
      '#allow_ownvote' => FALSE,
      '#ajax' => NULL,
      '#show_static_result' => FALSE,
      '#process' => [
        [$class, 'process'],
        [$class, 'processAjaxForm'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#widget' => [
        'name' => 'default',
      ],
      '#values' => [
        'vote_user' => 0,
        'vote_average' => 0,
        'vote_count' => 0,
      ],
      '#settings' => [
        'display_format' => 'average',
        'text_format' => 'none',
      ],
    ];
  }

  /**
   * Process handler for the fivestar form element.
   */
  public static function process(array &$element, FormStateInterface $form_state, &$complete_form) {
    $settings = $element['#settings'];
    $values = $element['#values'];
    $class[] = 'clearfix';

    $title = 'it';
    if (isset($settings['entity_id']) && isset($settings['entity_type'])) {
      $entity_id = $settings['entity_id'];
      $entity_type = $settings['entity_type'];
      $entity_manager = \Drupal::entityTypeManager();
      $entity = $entity_manager->getStorage($entity_type)->load($entity_id);
      $title = $entity->label();
    }
    elseif (isset($complete_form['#node'])) {
      $title = $complete_form['#node']->title;
    }

    $options = ['-' => t('Select rating')];
    for ($i = 1; $i <= $element['#stars']; $i++) {
      $this_value = ceil($i * 100 / $element['#stars']);
      $options[$this_value] = t('Give @title @star/@count', [
        '@title' => $title,
        '@star' => $i,
        '@count' => $element['#stars'],
      ]);
    }

    // Display clear button only if enabled.
    if ($element['#allow_clear'] == TRUE) {
      $options[0] = t('Cancel rating');
    }

    if (self::userCanVote($element)) {
      $element['vote'] = [
        '#type' => 'select',
        '#description' => self::getElementDescription($element),
        '#options' => $options,
        '#rating' => $values['vote_average'],
        '#required' => $element['#required'],
        '#attributes' => $element['#attributes'],
        '#default_value' => self::getElementDefaultValue($element),
        '#weight' => -2,
        '#ajax' => $element['#ajax'],
        '#parents' => $element['#parents'] ?? [],
      ];
    }

    // Show static rating output.
    else {
      $renderer = \Drupal::service('renderer');
      $static_stars = [
        '#theme' => 'fivestar_static',
        '#rating' => self::getElementDefaultValue($element),
        '#stars' => $element['#stars'],
        '#vote_type' => $element['#vote_type'],
        '#widget' => $element['#widget'],
      ];

      $element_static = [
        '#theme' => 'fivestar_static_element',
        '#star_display' => $renderer->render($static_stars),
        '#title' => '',
        '#description' => self::getElementDescription($element),
      ];

      $element['vote_statistic'] = [
        '#type' => 'markup',
        '#markup' => $renderer->render($element_static),
      ];
    }

    $class[] = "fivestar-{$settings['text_format']}-text";

    switch ($settings['display_format']) {
      case 'average':
        $class[] = 'fivestar-average-stars';
        break;

      case 'user':
        $class[] = 'fivestar-user-stars';
        break;

      case 'smart':
        $class[] = 'fivestar-smart-stars ' . ($values['vote_user'] ? 'fivestar-user-stars' : 'fivestar-average-stars');
        break;

      case 'dual':
        $class[] = 'fivestar-combo-stars';
        $static_average = [
          '#type' => 'fivestar_static',
          '#rating' => $values['vote_average'],
          '#stars' => $settings['stars'],
          '#vote_type' => $settings['vote_type'],
          '#widget' => $settings['widget'],
        ];

        if ($settings['text_format'] != 'none') {
          $static_description = [
            '#type' => 'fivestar_summary',
            '#average_rating' => $settings['text_format'] == 'user' ? NULL : ($values['vote_average'] ?? 0),
            '#votes' => $values['vote_count'] ?? 0,
            '#stars' => $settings['stars'],
          ];
        }
        else {
          $static_description = '&nbsp;';
        }
        $element_static = [
          '#type' => 'fivestar_static_element',
          '#title' => '',
          '#star_display' => $static_average,
          '#description' => $static_description,
        ];
        $element['average'] = [
          '#type' => 'markup',
          '#markup' => $element_static,
          '#weight' => -1,
        ];
        break;
    }

    $widget_name = mb_strtolower($element['#widget']['name']);
    $widget_name_kebab = str_replace('_', '-', $widget_name);

    $class[] = 'fivestar-form-item';
    $class[] = 'fivestar-' . $widget_name_kebab;

    if ($widget_name != 'default') {
      $element['#attached']['library'][] = \Drupal::service('fivestar.widget_manager')->getWidgetLibrary($widget_name);
    }

    $element['#prefix'] = '<div ' . new Attribute(['class' => $class]) . '>';
    $element['#suffix'] = '</div>';

    $element['#attached']['library'][] = 'fivestar/fivestar.base';

    return $element;
  }

  /**
   * Creates a fivestar_summary theme render array for an Element.
   *
   * @param array $element
   *   Fivestar element data, containing at least #stars, #values and
   *   #text_format keys. Other keys include #microdata, #display_format.
   *
   * @return array
   *   A Drupal render array containing a 'fivestar_summary' element suitable
   *   for describing this element.
   *   If the setting format is present and not 'none', and there are values,
   *   it will contain:
   *     #theme, #stars, #microdata, #votes, and one or both of #average_rating
   *     and #user_rating.
   *   Otherwise, it returns an empty array.
   */
  public static function getElementDescription(array $element) {
    if (empty($element['#settings']['text_format']) || empty($element['#values'])) {
      return [];
    }

    $settings = $element['#settings'];
    $values = $element['#values'];

    $base_element_data = [
      '#theme' => 'fivestar_summary',
      '#stars' => $element['#stars'],
      '#microdata' => $settings['microdata'] ?? NULL,
    ];

    switch ($settings['text_format']) {
      case 'user':
        return [
          '#user_rating' => $values['vote_user'],
          '#votes' => $settings['display_format'] == 'dual' ? NULL : $values['vote_count'],
        ] + $base_element_data;

      case 'average':
        return [
          '#average_rating' => $values['vote_average'],
          '#votes' => $values['vote_count'],
        ] + $base_element_data;

      case 'smart':
        return ($settings['display_format'] == 'dual' && !$values['vote_user']) ? [] : [
          '#user_rating' => $values['vote_user'],
          '#average_rating' => $values['vote_user'] ? NULL : $values['vote_average'],
          '#votes' => $settings['display_format'] == 'dual' ? NULL : $values['vote_count'],
        ] + $base_element_data;

      case 'dual':
        return [
          '#user_rating' => $values['vote_user'],
          '#average_rating' => $settings['display_format'] == 'dual' ? NULL : $values['vote_average'],
          '#votes' => $settings['display_format'] == 'dual' ? NULL : $values['vote_count'],
        ] + $base_element_data;

      case 'none':
        return [];
    }
  }

  /**
   * Determines if a user can vote on content.
   *
   * @param array $element
   *   Fivestar element data.
   *
   * @return bool
   *   TRUE if the user can vote, FALSE otherwise.
   */
  public static function userCanVote(array $element) {
    if ($element['#show_static_result']) {
      return FALSE;
    }
    if ($element['#allow_revote']) {
      return TRUE;
    }

    // Check if user have votes in current entity type.
    $vote_ids = [];
    $current_user = \Drupal::currentUser();
    $entity_type_id = $element['#settings']['entity_type_id'] ?? NULL;
    $entity_id = $element['#settings']['entity_id'] ?? NULL;

    // Check that we have entity details, allow if not.
    if (empty($entity_type_id) || empty($entity_id)) {
      return TRUE;
    }

    $vote_ids = \Drupal::entityQuery('vote')
      ->accessCheck(TRUE)
      ->condition('entity_type', $entity_type_id)
      ->condition('entity_id', $entity_id)
      ->condition('user_id', $current_user->id())
      ->execute();

    // If user voted before, return FALSE.
    if (!empty($vote_ids)) {
      return FALSE;
    }

    // Check allowed own vote.
    if ($element['#allow_ownvote']) {
      return TRUE;
    }

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    $owner_uid = $entity->getOwner()->id();

    return $owner_uid !== $current_user->id();
  }

  /**
   * Determines the correct initial widget value for a fivestar Element.
   *
   * @param array $element
   *   The fivestar element.
   *
   * @return float
   *   The default value for the element.
   */
  public static function getElementDefaultValue(array $element) {
    switch ($element['#settings']['display_format']) {
      case 'average':
        $widget_is_average = ($element['#settings']['display_format'] == 'average');
        $default_value = $widget_is_average && !empty($element['#values']['vote_average']) ?
          $element['#values']['vote_average'] :
          $element['#default_value'];
        break;

      case 'dual':
      case 'user':
        $default_value = $element['#values']['vote_user'];
        break;

      case 'smart':
        $default_value = $element['#values']['vote_user'] ?? $element['#values']['vote_average'];
        break;

      default:
        $default_value = $element['#default_value'];
    }

    for ($i = 0; $i <= $element['#stars']; $i++) {
      $this_value = ceil($i * 100 / $element['#stars']);
      $next_value = ceil(($i + 1) * 100 / $element['#stars']);

      // Round up the default value to the next exact star value if needed.
      if ($this_value < $default_value && $next_value > $default_value) {
        $default_value = $next_value;
      }
    }

    return $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return $input;
  }

}

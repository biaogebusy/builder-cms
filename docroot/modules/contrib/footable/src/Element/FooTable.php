<?php

namespace Drupal\footable\Element;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Table;

/**
 * Provides a render element for a FooTable.
 *
 * @FormElement("footable")
 */
class FooTable extends Table {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();

    $class = get_class($this);
    $info['#process'][] = [$class, 'processFooTable'];
    $info['#pre_render'][] = [$class, 'preRenderFooTable'];
    $info['#theme'] = 'footable';

    foreach (static::getProperties() as $key => $property) {
      $info['#' . $key] = $property['default'] ?? NULL;
    }

    return $info;
  }

  /**
   * Processes a FooTable element.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   container.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processFooTable(&$element, FormStateInterface $form_state, &$complete_form) {
    unset($element['#sticky'], $element['#responsive']);
    return $element;
  }

  /**
   *
   *
   * @param array $element
   *
   * @return array
   */
  public static function preRenderFooTable($element) {
    $element['#attributes']['class'][] = 'footable';

    /* @var \Drupal\footable\FooTableInterface $footable */
    $footable = Drupal::service('footable.footable');
    $element['#attached']['library'][] = $footable->getLibrary();

    // Add FooTable properties.
    foreach (static::getProperties() as $key => $property) {
      $default = $property['default'] ?? NULL;
      $value = $element['#' . $key] ?? $default;

      if ($value !== $default) {
        if (is_bool($value)) {
          $value = $value ? 'true' : 'false';
        }

        $element['#attributes']['data-' . $property['key']] = $value;
      }
    }

    if (isset($element['#header']) && is_array($element['#header'])) {
      foreach ($element['#header'] as $key => &$header) {
        if (!is_array($header) && Element::child($key)) {
          $header = [
            'data' => $header,
          ];
        }

        if ($footable = $header['footable'] ?? NULL) {
          unset($header['footable']);

          if (isset($footable['sort'])) {
            $element['#attributes']['data-sorting'] = 'true';

            $header['data-sortable'] = 'true';
            $header['data-direction'] = $footable['sort'];
            $header['data-breakpoints'] = 'all';
          }
        }
      }
    }

    return $element;
  }

  /**
   * Retrieve a list of available FooTable properties.
   *
   * @return array
   *   An associative array keyed by property id, containing:
   *   - key: The FooTable attribute key.
   *   - default: The default value of the property.
   */
  protected static function getProperties() {
    return [
      'empty' => [
        'key' => 'empty',
        'default' => t('No Results'),
      ],
      'expand_all' => [
        'key' => 'expand-all',
        'default' => FALSE,
      ],
      'expand_first' => [
        'key' => 'expand-first',
        'default' => FALSE,
      ],
      'show_header' => [
        'key' => 'show-header',
        'default' => FALSE,
      ],
      'show_toggle' => [
        'key' => 'show-toggle',
        'default' => TRUE,
      ],
      'toggle_column' => [
        'key' => 'toggle-column',
        'default' => 'first',
      ],
      'use_parent_width' => [
        'key' => 'use-parent-width',
        'default' => FALSE,
      ],

      // Filtering.
      'filtering' => [
        'key' => 'filtering',
        'default' => FALSE,
      ],
      'filter_container' => [
        'key' => 'filter-form-container',
      ],
      'filter_delay' => [
        'key' => 'filter-delay',
        'default' => 1200,
      ],
      'filter_dropdown_title' => [
        'key' => 'filter-dropdown-title',
      ],
      'filter_exact_match' => [
        'key' => 'filter-exact-match',
        'default' => FALSE,
      ],
      'filter_focus' => [
        'key' => 'filter-focus',
        'default' => TRUE,
      ],
      'filter_ignore_case' => [
        'key' => 'filter-ignore-case',
        'default' => TRUE,
      ],
      'filter_min' => [
        'key' => 'filter-min',
        'default' => 1,
      ],
      'filter_placeholder' => [
        'key' => 'filter-placeholder',
        'default' => t('Search'),
      ],
      'filter_position' => [
        'key' => 'filter-position',
        'default' => 'right',
      ],
      'filter_space' => [
        'key' => 'filter-space',
        'default' => 'AND',
      ],

      // Sorting.
      'sorting' => [
        'key' => 'sorting',
        'default' => FALSE,
      ],

      // Paging.
      'paging' => [
        'key' => 'paging',
        'default' => FALSE,
      ],
      'paging_container' => [
        'key' => 'paging-container',
      ],
      'paging_count_format' => [
        'key' => 'paging-container',
        'default' => '{CP} of {TP}',
      ],
      'paging_current' => [
        'key' => 'paging-current',
        'default' => 1,
      ],
      'paging_limit' => [
        'key' => 'paging-limit',
        'default' => 5,
      ],
      'paging_position' => [
        'key' => 'paging-position',
        'default' => 'center',
      ],
      'paging_size' => [
        'key' => 'paging-size',
        'default' => 10,
      ],

      // State.
      'state' => [
        'key' => 'state',
        'default' => FALSE,
      ],
      'state_filtering' => [
        'key' => 'state-filtering',
        'default' => TRUE,
      ],
      'state_paging' => [
        'key' => 'state-paging',
        'default' => TRUE,
      ],
      'state_sorting' => [
        'key' => 'state-sorting',
        'default' => TRUE,
      ],
      'state_key' => [
        'key' => 'state-key',
      ],
    ];
  }

}

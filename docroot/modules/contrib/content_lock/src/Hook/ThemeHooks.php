<?php

namespace Drupal\content_lock\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Theme hooks for the Content Lock module.
 */
class ThemeHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() : array {
    return [
      'content_lock_settings_entities' => [
        'render element' => 'element',
        'initial preprocess' => static::class . ':preprocessContentLockSettingsEntities',
      ],
    ];
  }

  /**
   * Prepares variables for content lock entity settings templates.
   *
   * Default template: content-lock-settings-entities.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - element: An associative array containing the properties of the element.
   *     Properties used: #title.
   */
  public function preprocessContentLockSettingsEntities(array &$variables): void {
    $element = $variables['element'];

    $header = [
      [
        'data' => $element['bundles']['#title'],
        'class' => ['bundle'],
      ],
      [
        'data' => $this->t('Configuration'),
        'class' => ['operations'],
      ],
    ];

    $rows = [];
    foreach (Element::children($element['bundles']) as $bundle) {
      $rows[$bundle] = [
        'data' => [
          [
            'data' => $element['bundles'][$bundle],
            'class' => ['bundle'],
          ],
        ],
        'class' => [],
      ];
      if ($bundle == '*') {
        $rows[$bundle]['data'][] = [
          'data' => $element['settings'],
          'class' => ['operations'],
        ];
      }
      else {
        $rows[$bundle]['data'][] = [
          'data' => $this->t('Uses "all" settings'),
          'class' => ['operations'],
        ];
        $rows[$bundle]['class'][] = 'bundle-settings';
      }
    }

    $variables['title'] = $element['#title'];
    $variables['build'] = [
      '#header' => $header,
      '#rows' => $rows,
      '#type' => 'table',
    ];
  }

}

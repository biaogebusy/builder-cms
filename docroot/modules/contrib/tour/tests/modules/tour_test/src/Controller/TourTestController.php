<?php

namespace Drupal\tour_test\Controller;

/**
 * Controller routines for tour_test routes.
 */
class TourTestController {

  /**
   * Outputs some content for testing tours.
   *
   * @param string $locale
   *   (optional) Dummy locale variable for testing routing parameters. Defaults
   *   to 'foo'.
   *
   * @return array
   *   Array of markup.
   */
  public function tourTest1(string $locale = 'foo'): array {
    return [
      'tip-1' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'tour-test-1',
        ],
        '#children' => t('Where does the rain in Spain fail?'),
      ],
      'tip-3' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'tour-test-3',
        ],
        '#children' => t('Tip created now?'),
      ],
      'tip-4' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'tour-test-4',
        ],
        '#children' => t('Tip created later?'),
      ],
      'tip-5' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['tour-test-5'],
        ],
        '#children' => t('Tip created later?'),
      ],
      'code-tip-1' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'tour-code-test-1',
        ],
        '#children' => t('Tip created now?'),
      ],
    ];
  }

  /**
   * Outputs some content for testing tours.
   */
  public function tourTest2(): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'tour-test-2',
      ],
      '#children' => t('Pangram example'),
    ];

  }

  /**
   * Callback method for 'tour_test.4' route.
   *
   * @return array
   *   The render array.
   */
  public function tourTest4(): array {
    return [
      'trigger1' => [
        '#type' => 'button',
        '#value' => 'Trigger 1',
        '#attributes' => [
          'class' => ['js-tour-start-toolbar'],
          'role' => 'button',
        ],
      ],
      'trigger2' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => 'Trigger 2',
        '#attributes' => [
          'id' => 'trigger2',
          'class' => ['js-tour-start-toolbar'],
        ],
      ],
      'tip-1' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'tour-test-1',
        ],
        '#children' => t('The quick brown fox jumps over the lazy dog'),
      ],
      'tip-2' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'tour-test-2',
        ],
        '#children' => t('Now is the time for all good men to come to the aid of the party'),
      ],
    ];
  }

}

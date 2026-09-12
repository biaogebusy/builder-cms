<?php

declare(strict_types=1);

namespace Drupal\tour;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines a class for lazy building render arrays.
 *
 * @internal
 */
final class LazyBuilders implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * Constructs LazyBuilders object.
   *
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $elementInfo
   *   Element info.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param mixed $tourHelper
   *   Helper methods for the tour module.
   */
  public function __construct(
    protected ElementInfoManagerInterface $elementInfo,
    protected ConfigFactoryInterface $configFactory,
    protected TourHelper $tourHelper,
  ) {
  }

  /**
   * Render tour.
   *
   * @param bool $noTips
   *   Indicates if the current page has any tour.
   *
   * @return array
   *   Render array.
   */
  public function renderTour(bool $noTips = FALSE): array {
    $classes = [
      'toolbar-icon',
      'toolbar-item',
      'toolbar-icon-help',
      'js-tour-start-toolbar',
    ];

    if ($noTips) {
      $classes = array_merge($classes, ['toolbar-tab-empty']);
    }

    $tour_avail_text = $this->tourHelper->getTourLabels()['tour_avail_text'];
    $tour_no_avail_text = $this->tourHelper->getTourLabels()['tour_no_avail_text'];

    return [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#cache' => [
        'contexts' => ['url'],
        'tags' => ['tour_settings'],
      ],
      '#value' => $noTips ? $tour_no_avail_text : $tour_avail_text,
      '#attributes' => [
        'class' => $classes,
        'aria-haspopup' => 'dialog',
        'type' => 'button',
        'aria-disabled' => $noTips ? 'true' : 'false',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['renderTour'];
  }

}

<?php

declare(strict_types=1);

namespace Drupal\tour\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tour\TourInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for tour recap page.
 */
class TourRecapController extends ControllerBase {

  public function __construct(
    protected RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('renderer'),
    );
  }

  /**
   * Displays a recap of tour steps.
   *
   * @param \Drupal\tour\TourInterface $tour
   *   The tour entity.
   *
   * @return array
   *   A render array for the recap page.
   */
  public function recap(TourInterface $tour): array {
    $tips = $tour->getTips();
    $items = [];

    foreach ($tips as $tip) {
      $body_render_array = $tip->getBody();
      $body = (string) $this->renderer->renderInIsolation($body_render_array);

      $items[] = [
        'title' => $tip->getLabel(),
        'body' => $body,
      ];
    }

    return [
      '#theme' => 'tour_recap',
      '#tour' => $tour,
      '#tips' => $items,
    ];
  }

  /**
   * Title callback for the recap page.
   *
   * @param \Drupal\tour\TourInterface $tour
   *   The tour entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The page title.
   */
  public function title(TourInterface $tour): TranslatableMarkup {
    return $this->t('@tour - Steps', ['@tour' => $tour->label()]);
  }

}

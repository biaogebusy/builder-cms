<?php

namespace Drupal\acquia_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Acquia Start Wizard Page.
 */
class StartController extends ControllerBase {

  /**
   * Callback for acquia_connector.start route.
   */
  public function info() {
    $build = [];

    $build['#title'] = $this->t('Get an Acquia Cloud Free subscription');

    if (version_compare(\Drupal::VERSION, '9.3', '>=')) {
      // phpcs:ignore
      $path = \Drupal::service('extension.path.resolver')->getPath('module', 'acquia_connector');
    }
    else {
      // @phpstan-ignore-next-line
      $path = drupal_get_path('module', 'acquia_connector');
    }

    $build['#attached']['library'][] = 'acquia_connector/acquia_connector.form';

    $banner = [
      '#theme' => 'image',
      '#attributes' => [
        'src' => Url::fromUri('base:' . $path . '/images/action.png', ['absolute' => TRUE])->toString(),
      ],
    ];
    $uri = Url::fromRoute('acquia_connector.setup', [], ['absolute' => TRUE])->toString();

    if (version_compare(\Drupal::VERSION, '9.3', '>=')) {
      // phpcs:ignore
      $rendered = \Drupal::service('renderer')->render($banner);
    }
    else {
      // @phpstan-ignore-next-line
      $rendered = render($banner);
    }
    $banner = '<a href="' . $uri . '">' . $rendered . '</a>';

    $output = '<div class="an-start-form">';
    $output .= '<div class="an-pg-container">';
    $output .= '<div class="an-wrapper">';
    $output .= '<h2 class="an-info-header">' . $this->t('Acquia Subscription', ['@acquia-network' => 'https://www.acquia.com/customer-success']) . '</h2>';
    $output .= '<p class="an-slogan">' . $this->t('A suite of products and services to create & maintain killer web experiences built on Drupal') . '</p>';
    $output .= '<div class="an-info-box">';
    $output .= '<div class="cell with-arrow an-left">';
    $output .= '<h2 class="cell-title"><i>' . $this->t('Answers you need') . '</i></h2>';
    $image = [
      '#theme' => 'image',
      '#attributes' => [
        'src' => Url::fromUri('base:' . $path . '/images/icon-library.png', ['absolute' => TRUE])->toString(),
      ],
    ];
    if (version_compare(\Drupal::VERSION, '9.3', '>=')) {
      // phpcs:ignore
      $rendered = \Drupal::service('renderer')->render($image);
    }
    else {
      // @phpstan-ignore-next-line
      $rendered = render($image);
    }
    $output .= '<a href="https://docs.acquia.com" target="_blank">' . $rendered . '</a>';
    $output .= '<p class="cell-p">' . $this->t("Tap the collective knowledge of Acquia’s technical support team & partners.") . '</p>';
    $output .= '</div>';
    $output .= '<div class="cell with-arrow an-center">';
    $output .= '<h2 class="cell-title"><i>' . $this->t('Tools to extend your site') . '</i></h2>';
    $image = [
      '#theme' => 'image',
      '#attributes' => [
        'src' => Url::fromUri('base:' . $path . '/images/icon-tools.png', ['absolute' => TRUE])->toString(),
      ],
    ];
    if (version_compare(\Drupal::VERSION, '9.3', '>=')) {
      // phpcs:ignore
      $rendered = \Drupal::service('renderer')->render($image);
    }
    else {
      // @phpstan-ignore-next-line
      $rendered = render($image);
    }
    $output .= '<a href="https://www.acquia.com/customer-success" target="_blank">' . $rendered . '</a>';
    $output .= '<p class="cell-p">' . $this->t('Enhance and extend your site with an array of <a href=":services" target="_blank">services</a> from Acquia & our partners.', [':services' => 'https://www.acquia.com/products-services/acquia-cloud']) . '</p>';
    $output .= '</div>';
    $output .= '<div class="cell an-right">';
    $output .= '<h2 class="cell-title"><i>' . $this->t('Support when you want it') . '</i></h2>';
    $image = [
      '#theme' => 'image',
      '#attributes' => [
        'src' => Url::fromUri('base:' . $path . '/images/icon-support.png', ['absolute' => TRUE])->toString(),
      ],
    ];
    if (version_compare(\Drupal::VERSION, '9.3', '>=')) {
      // phpcs:ignore
      $rendered = \Drupal::service('renderer')->render($image);
    }
    else {
      // @phpstan-ignore-next-line
      $rendered = render($image);
    }
    $output .= '<a href="https://support.acquia.com" target="_blank">' . $rendered . '</a>';
    $output .= '<p class="cell-p">' . $this->t("Experienced Drupalists are available to support you whenever you need it.") . '</p>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '<div class="an-pg-banner">';
    $output .= $banner;
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';
    $build['output'] = [
      '#markup' => $output,
    ];

    return $build;
  }

}

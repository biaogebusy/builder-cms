<?php

namespace Drupal\ai\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Custom breadcrumb builder for model settings page.
 */
class AiModelSettingsBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    // Apply this breadcrumb builder to your custom form route.
    return in_array($route_match->getRouteName(), [
      'ai.edit_model_settings_form',
      'ai.create_model_settings_form',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();

    // Define breadcrumb links.
    $links = [
      Link::createFromRoute($this->t('Home'), '<front>'),
      Link::createFromRoute($this->t('Administration'), 'system.admin'),
      Link::createFromRoute($this->t('Configuration'), 'system.admin_config'),
      Link::createFromRoute($this->t('AI'), 'ai.settings.menu'),
      Link::createFromRoute($this->t('AI Providers'), 'ai.admin_providers'),
    ];

    // Set the breadcrumb links.
    $breadcrumb->setLinks($links);
    $breadcrumb->addCacheContexts(['url.path']);

    return $breadcrumb;
  }

}

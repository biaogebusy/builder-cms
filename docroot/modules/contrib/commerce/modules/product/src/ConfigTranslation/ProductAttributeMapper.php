<?php

namespace Drupal\commerce_product\ConfigTranslation;

use Drupal\config_translation\ConfigEntityMapper;
use Symfony\Component\Routing\Route;

/**
 * Provides a configuration mapper for product attributes.
 */
class ProductAttributeMapper extends ConfigEntityMapper {

  /**
   * {@inheritdoc}
   */
  public function getAddRoute() {
    $route = parent::getAddRoute();
    $route->setDefault('_form', '\Drupal\commerce_product\Form\ProductAttributeTranslationAddForm');
    $this->addTranslationFormAccessCheck($route);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getEditRoute() {
    $route = parent::getEditRoute();
    $route->setDefault('_form', '\Drupal\commerce_product\Form\ProductAttributeTranslationEditForm');
    $this->addTranslationFormAccessCheck($route);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeleteRoute() {
    $route = parent::getDeleteRoute();
    $this->addTranslationFormAccessCheck($route);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverviewRoute() {
    $route = parent::getOverviewRoute();
    $route_requirements = $route->getRequirements();
    unset($route_requirements['_config_translation_overview_access']);
    $route_requirements['_product_attribute_translation_access'] = 'TRUE';
    $route->setRequirements($route_requirements);
    return $route;
  }

  /**
   * Modifies route to use custom access check.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route object.
   */
  protected function addTranslationFormAccessCheck(Route &$route): void {
    $route_requirements = $route->getRequirements();
    unset($route_requirements['_config_translation_form_access']);
    $route_requirements['_product_attribute_translation_form_access'] = 'TRUE';
    $route->setRequirements($route_requirements);
  }

}

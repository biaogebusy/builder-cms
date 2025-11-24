<?php

namespace Drupal\commerce_product;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\commerce_product\Access\ProductAttributeTranslationAccessCheck;
use Drupal\commerce_product\Access\ProductAttributeTranslationFormAccessCheck;
use Drupal\commerce_product\EventSubscriber\VariationFieldComponentSubscriber;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Swap field rendered when layout builder module is on.
 */
class CommerceProductServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Get list of modules.
    $modules = $container->getParameter('container.modules');

    // Check if there is layout builder and swap field renderer service.
    if (isset($modules['layout_builder'])) {
      $definition = $container->getDefinition('commerce_product.variation_field_renderer');
      $definition
        ->setClass(ProductVariationFieldRendererLayoutBuilder::class)
        ->addMethodCall('setEntityDisplayRepository', [new Reference('entity_display.repository')]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Register new service only when layout builder module is enabled.
    $modules = $container->getParameter('container.modules');
    if (isset($modules['layout_builder'])) {
      $container->register('commerce_product.variation_field_component_subscriber')
        ->setClass(VariationFieldComponentSubscriber::class)
        ->addTag('event_subscriber');
    }

    if (isset($modules['config_translation'])) {
      $parent = $container->getDefinition('config_translation.access.overview');
      $container->register('access_check.product_attribute_translation', ProductAttributeTranslationAccessCheck::class)
        ->setArguments($parent->getArguments())
        ->addTag('access_check', ['applies_to' => '_product_attribute_translation_access']);

      $parent = $container->getDefinition('config_translation.access.form');
      $container->register('access_check.product_attribute_translation.form', ProductAttributeTranslationFormAccessCheck::class)
        ->setArguments($parent->getArguments())
        ->addArgument(new Reference('access_check.product_attribute_translation'))
        ->addTag('access_check', ['applies_to' => '_product_attribute_translation_form_access']);
    }
  }

}

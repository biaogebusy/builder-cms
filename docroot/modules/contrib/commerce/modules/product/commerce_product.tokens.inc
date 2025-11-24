<?php

/**
 * @file
 * Builds placeholder replacement tokens for product data.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\commerce_product\Entity\ProductInterface;

/**
 * Implements hook_token_info().
 */
function commerce_product_token_info() {
  $info = [];

  $info['tokens']['commerce_product']['default_variation'] = [
    'name' => t('Default product variation'),
    'description' => t('Returns the default product variation for a product.'),
    'type' => 'commerce_product_variation',
  ];

  $info['tokens']['commerce_product']['current_variation'] = [
    'name' => t('Current product variation'),
    'description' => t('Returns the current product variation for a product which can change based on the query string.'),
    'type' => 'commerce_product_variation',
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function commerce_product_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type === 'commerce_product' && !empty($data['commerce_product'])) {
    $commerce_product = $data['commerce_product'];
    assert($commerce_product instanceof ProductInterface);
    if (($default_variation_tokens = \Drupal::token()->findWithPrefix($tokens, 'default_variation'))) {
      $default_variation = $commerce_product->getDefaultVariation();
      if ($default_variation) {
        $bubbleable_metadata->addCacheableDependency($default_variation);
      }
      $replacements += \Drupal::token()->generate('commerce_product_variation', $default_variation_tokens, ['commerce_product_variation' => $default_variation], $options, $bubbleable_metadata);
    }

    if (($current_variation_tokens = \Drupal::token()->findWithPrefix($tokens, 'current_variation'))) {
      /** @var \Drupal\commerce_product\ProductVariationStorageInterface $variation_storage */
      $variation_storage = \Drupal::service('entity_type.manager')->getStorage('commerce_product_variation');
      $current_variation = $variation_storage->loadFromContext($commerce_product);
      if ($current_variation) {
        $bubbleable_metadata->addCacheableDependency($current_variation);
      }
      $replacements += \Drupal::token()->generate('commerce_product_variation', $current_variation_tokens, ['commerce_product_variation' => $current_variation], $options, $bubbleable_metadata);
    }
  }

  return $replacements;
}

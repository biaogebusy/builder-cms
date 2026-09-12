<?php

namespace Drupal\commerce_product\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * Theme hook implementations for Commerce Product.
 */
class CommerceProductThemeHooks {

  /**
   * Constructs a new CommerceProductThemeHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_product_form' => [
        'render element' => 'form',
      ],
      'commerce_product' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommerceProduct',
      ],
      'commerce_product_variation' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommerceProductVariation',
      ],
      'commerce_product_attribute_value' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommerceProductAttributeValue',
      ],
    ];
  }

  /**
   * Implements hook_theme_registry_alter().
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(&$theme_registry): void {
    // The preprocess function must run after quickedit_preprocess_field().
    // @todo check if this can be removed considering quickedit is no longer in
    // core.
    $theme_registry['field']['preprocess functions'][] = 'commerce_product_remove_quickedit';
  }

  /**
   * Prepares variables for product templates.
   *
   * Default template: commerce-product.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommerceProduct(array &$variables): void {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $product = $variables['elements']['#commerce_product'];

    $variables['product_entity'] = $product;
    $variables['product_url'] = $product->isNew() ? '' : $product->toUrl();
    $variables['product'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['product'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Prepares variables for product variation templates.
   *
   * Default template: commerce-product-variation.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommerceProductVariation(array &$variables): void {
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $product_variation */
    $product_variation = $variables['elements']['#commerce_product_variation'];
    $product = $product_variation->getProduct();

    $variables['product_variation_entity'] = $product_variation;
    $variables['product_url'] = '';
    if ($product && !$product->isNew()) {
      $variables['product_url'] = $product->toUrl();
      // The product variation url cannot be properly generated if it doesn't
      // reference a valid product.
      $variables['product_variation_url'] = $product_variation->toUrl();
    }

    $variables['product_variation'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['product_variation'][$key] = $variables['elements'][$key];
    }

    // Load the active variation from the context:
    /** @var \Drupal\commerce_product\ProductVariationStorageInterface $variation_storage */
    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
    $active_variation = $variation_storage->loadFromContext($product);
    // If the active variation is the same as the current variation, set the active variation flag:
    $variables['is_active'] = (int) $active_variation?->id() === (int) $product_variation->id();
  }

  /**
   * Prepares variables for product attribute value templates.
   *
   * Default template: commerce-product-attribute-value.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommerceProductAttributeValue(array &$variables): void {
    /** @var \Drupal\commerce_product\Entity\ProductAttributeValueInterface $attribute_value */
    $attribute_value = $variables['elements']['#commerce_product_attribute_value'];

    $variables['product_attribute_value_entity'] = $attribute_value;
    $variables['product_attribute_value'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['product_attribute_value'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Implements hook_theme_suggestions_commerce_product().
   */
  #[Hook('theme_suggestions_commerce_product')]
  public function themeSuggestionsCommerceProduct(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_product', $variables);
  }

  /**
   * Implements hook_theme_suggestions_commerce_product_variation().
   */
  #[Hook('theme_suggestions_commerce_product_variation')]
  public function themeSuggestionsCommerceProductVariation(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_product_variation', $variables);
  }

  /**
   * Implements hook_theme_suggestions_commerce_product_attribute_value().
   */
  #[Hook('theme_suggestions_commerce_product_attribute_value')]
  public function themeSuggestionsCommerceProductAttributeValue(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_product_attribute_value', $variables);
  }

}

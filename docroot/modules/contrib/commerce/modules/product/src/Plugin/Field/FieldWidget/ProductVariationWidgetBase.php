<?php

namespace Drupal\commerce_product\Plugin\Field\FieldWidget;

use Drupal\commerce_product\ProductVariationStorageInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\commerce_product\Ajax\UpdateProductUrlCommand;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Event\ProductEvents;
use Drupal\commerce_product\Event\ProductVariationAjaxChangeEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base structure for product variation widgets.
 *
 * Product variation widget forms depends on the 'product' being present in
 * $form_state.
 *
 * @see \Drupal\commerce_product\Plugin\Field\FieldFormatter\AddToCartFormatter::viewElements().
 */
abstract class ProductVariationWidgetBase extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityRepository = $container->get('entity.repository');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_order_item' && $field_name == 'purchased_entity';
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Assumes that the variation ID comes from an $element['variation'] built
    // in formElement().
    foreach ($values as $key => $value) {
      $values[$key] = [
        'target_id' => $value['variation'],
      ];
    }

    return $values;
  }

  /**
   * #ajax callback: Replaces the rendered fields on variation change.
   *
   * Assumes the existence of a 'selected_variation' in $form_state.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Render\MainContent\MainContentRendererInterface $ajax_renderer */
    $ajax_renderer = \Drupal::service('main_content_renderer.ajax');
    $request = \Drupal::request();
    $route_match = \Drupal::service('current_route_match');
    /** @var \Drupal\Core\Ajax\AjaxResponse $response */
    $response = $ajax_renderer->renderResponse($form, $request, $route_match);

    $variation = ProductVariation::load($form_state->get('selected_variation'));
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $product = $form_state->get('product');
    if ($variation->hasTranslation($product->language()->getId())) {
      $variation = $variation->getTranslation($product->language()->getId());
    }
    /** @var \Drupal\commerce_product\ProductVariationFieldRendererInterface $variation_field_renderer */
    $variation_field_renderer = \Drupal::service('commerce_product.variation_field_renderer');
    $view_mode = $form_state->get('view_mode');
    $variation_field_renderer->replaceRenderedFields($response, $variation, $view_mode);
    // Update Product URL to include variation query parameter.
    $response->addCommand(new UpdateProductUrlCommand($variation->id()));

    // Allow modules to add arbitrary ajax commands to the response.
    $event = new ProductVariationAjaxChangeEvent($variation, $response, $view_mode);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch($event, ProductEvents::PRODUCT_VARIATION_AJAX_CHANGE);

    return $response;
  }

  /**
   * Gets the default variation for the widget.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   * @param array $variations
   *   An array of available variations.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface
   *   The default variation.
   */
  protected function getDefaultVariation(ProductInterface $product, array $variations) {
    $langcode = $product->language()->getId();
    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
    assert($variation_storage instanceof ProductVariationStorageInterface);
    $selected_variation = $variation_storage->loadFromContext($product);
    $selected_variation = $this->entityRepository->getTranslationFromContext($selected_variation, $langcode);
    // The returned variation must also be enabled.
    if (!isset($variations[$selected_variation->id()])) {
      $selected_variation = reset($variations);
    }
    return $selected_variation;
  }

  /**
   * Gets the enabled variations for the product.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface[]
   *   An array of variations.
   */
  protected function loadEnabledVariations(ProductInterface $product) {
    $langcode = $product->language()->getId();
    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
    assert($variation_storage instanceof ProductVariationStorageInterface);
    $variations = $variation_storage->loadEnabled($product);
    foreach ($variations as $key => $variation) {
      $variations[$key] = $this->entityRepository->getTranslationFromContext($variation, $langcode);
    }
    return $variations;
  }

}

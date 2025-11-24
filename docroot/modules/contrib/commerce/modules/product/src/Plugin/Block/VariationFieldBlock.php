<?php

namespace Drupal\commerce_product\Plugin\Block;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Variation field block.
 *
 * Specific block class for Layout Builder's field block and variations to
 * ensure field replacement works.
 */
class VariationFieldBlock extends FieldBlock {

  /**
   * The variation field renderer.
   *
   * @var \Drupal\commerce_product\ProductVariationFieldRendererInterface
   */
  protected $productVariationFieldRenderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->productVariationFieldRenderer = $container->get('commerce_product.variation_field_renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $display_settings = $this->getConfiguration()['formatter'];
    $entity = $this->getEntity();
    assert($entity instanceof ProductVariationInterface);
    try {
      $build = $this->productVariationFieldRenderer->renderField($this->fieldName, $entity, $display_settings);
    }
    catch (\Exception $e) {
      $build = [];
      $this->logger->warning('The field "%field" failed to render with the error of "%error".', ['%field' => $this->fieldName, '%error' => $e->getMessage()]);
    }
    CacheableMetadata::createFromObject($this)->applyTo($build);
    return $build;
  }

}

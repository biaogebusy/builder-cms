<?php

namespace Drupal\commerce_product\EventSubscriber;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_product\Plugin\Block\VariationFieldBlock;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add variation context for block build based on product context.
 *
 * @internal
 *   Tagged services are internal.
 */
class VariationFieldComponentSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY => [
        'onBuildRender',
        110,
      ],
    ];
  }

  /**
   * Set variation context when it is possible for VariationFieldBlock.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
    $block = $event->getPlugin();
    if (!($block instanceof VariationFieldBlock)) {
      return;
    }

    $contexts = $event->getContexts();
    if (isset($contexts['layout_builder.entity'])) {
      $product = $contexts['layout_builder.entity']->getContextValue();
      if (
        !($product instanceof ProductInterface) ||
        $product->isNew() ||
        !$product->getDefaultVariation()
      ) {
        return;
      }

      $variation_context_name = '@commerce_product.product_variation_route_context:commerce_product_variation';
      $variation_context = $contexts[$variation_context_name]->getContextValue();
      if (
        !($variation_context instanceof ProductVariationInterface) ||
        $variation_context->isNew()
      ) {
        $context_definition = new EntityContextDefinition('entity:commerce_product_variation', $this->t('Product variation'));
        $context = new Context($context_definition, $product->getDefaultVariation());
        $block->setContext('entity', $context);
      }
    }
  }

}

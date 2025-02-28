<?php

namespace Drupal\commerce_product\ContextProvider;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\ProductVariationStorageInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\layout_builder\DefaultsSectionStorageInterface;
use Drupal\layout_builder\Entity\SampleEntityGeneratorInterface;
use Drupal\layout_builder\OverridesSectionStorageInterface;

/**
 * Provides a product variation context.
 */
class ProductVariationContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The sample entity generator.
   *
   * @var \Drupal\layout_builder\Entity\SampleEntityGeneratorInterface|null
   */
  protected $sampleEntityGenerator;

  /**
   * Constructs a new ProductVariationContext object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Set the sample entity generator.
   *
   * @param \Drupal\layout_builder\Entity\SampleEntityGeneratorInterface $sample_entity_generator
   *   The sample entity generator.
   */
  public function setSampleEntityGenerator(SampleEntityGeneratorInterface $sample_entity_generator) {
    $this->sampleEntityGenerator = $sample_entity_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $context_definition = new EntityContextDefinition('entity:commerce_product_variation', new TranslatableMarkup('Product variation'));
    $value = $this->routeMatch->getParameter('commerce_product_variation');
    if ($value === NULL) {
      $product = $this->routeMatch->getParameter('commerce_product');
      if ($product instanceof ProductInterface) {
        $product_variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');
        assert($product_variation_storage instanceof ProductVariationStorageInterface);
        $value = $product_variation_storage->loadFromContext($product);
        if ($value === NULL) {
          $product_type = ProductType::load($product->bundle());
          $variation_types = $product_type->getVariationTypeIds();
          $value = $product_variation_storage->create([
            'type' => reset($variation_types),
          ]);
        }
      }
      // @todo Simplify this logic once EntityTargetInterface is available
      // @see https://www.drupal.org/project/drupal/issues/3054490
      elseif (strpos((string) $this->routeMatch->getRouteName(), 'layout_builder') !== FALSE) {
        /** @var \Drupal\layout_builder\SectionStorageInterface $section_storage */
        $section_storage = $this->routeMatch->getParameter('section_storage');
        if ($section_storage instanceof DefaultsSectionStorageInterface) {
          $context = $section_storage->getContextValue('display');
          assert($context instanceof EntityDisplayInterface);
          if ($context->getTargetEntityTypeId() === 'commerce_product') {
            $product_type = ProductType::load($context->getTargetBundle());
            $variation_types = $product_type->getVariationTypeIds();
            $value = $this->sampleEntityGenerator->get('commerce_product_variation', reset($variation_types));
          }
        }
        elseif ($section_storage instanceof OverridesSectionStorageInterface) {
          $context = $section_storage->getContextValue('entity');
          if ($context instanceof ProductInterface) {
            $value = $context->getDefaultVariation();
            if ($value === NULL) {
              $product_type = ProductType::load($context->bundle());
              $variation_types = $product_type->getVariationTypeIds();
              $value = $this->sampleEntityGenerator->get('commerce_product_variation', reset($variation_types));
            }
          }
        }
      }
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);
    $context = new Context($context_definition, $value);
    $context->addCacheableDependency($cacheability);

    return ['commerce_product_variation' => $context];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    // @todo Remove this route name check once Layout Builder is fixed.
    if ($this->routeMatch->getRouteName() !== NULL) {
      // This handles when the context is called via getAvailableContexts in
      // the entity param converter while route negotiation is being handled.
      //
      // This is a pretty big hack to workaround the fact Layout Builder does
      // not properly invoke getRuntimeContexts but assumes getAvailableContexts
      // will have populated values in the contexts returned.
      //
      // @see https://www.drupal.org/project/drupal/issues/3099968
      // @see \Drupal\Core\ParamConverter\EntityConverter::convert
      return $this->getRuntimeContexts([]);
    }
    $context = EntityContext::fromEntityTypeId(
      'commerce_product_variation',
      $this->t('Product variation from current product.')
    );
    return ['commerce_product_variation' => $context];
  }

}

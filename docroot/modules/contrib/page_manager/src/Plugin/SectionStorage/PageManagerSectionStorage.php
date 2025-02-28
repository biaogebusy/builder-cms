<?php

namespace Drupal\page_manager\Plugin\SectionStorage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\Url;
use Drupal\layout_builder\Entity\SampleEntityGeneratorInterface;
use Drupal\layout_builder\Plugin\SectionStorage\SectionStorageBase;
use Drupal\page_manager\Entity\PageVariant;
use Drupal\page_manager\Plugin\DisplayVariant\LayoutBuilderDisplayVariant;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines the 'page_manager' section storage type.
 *
 * @SectionStorage(
 *   id = "page_manager",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:page_variant"),
 *   },
 * )
 */
class PageManagerSectionStorage extends SectionStorageBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The sample entity generator.
   *
   * @var \Drupal\layout_builder\Entity\SampleEntityGeneratorInterface
   */
  protected $sampleEntityGenerator;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstore;

  /**
   * The entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * PageManagerSectionStorage constructor.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\layout_builder\Entity\SampleEntityGeneratorInterface $sample_entity_generator
   *   The sample entity generator.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity bundle information.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, SampleEntityGeneratorInterface $sample_entity_generator, SharedTempStoreFactory $tempstore, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->sampleEntityGenerator = $sample_entity_generator;
    $this->tempstore = $tempstore;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('layout_builder.sample_entity_generator'),
      $container->get('tempstore.shared'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getSectionList() {
    return $this->getContextValue('entity')->getVariantPlugin();
  }

  /**
   * Gets the page variant entity.
   *
   * @return \Drupal\page_manager\Entity\PageVariant
   *   The page variant entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getPageVariant() {
    return $this->getContextValue('entity');
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageId() {
    return $this->getContextValue('entity')->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl() {
    return Url::fromUri($this->getPageVariant()->getPage()->getPath());
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutBuilderUrl($rel = 'view') {
    return Url::fromRoute("layout_builder.page_manager.view", ['page_variant' => $this->getPageVariant()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildRoutes(RouteCollection $collection) {
    $path = '/admin/structure/page_manager/{page_variant}/layout';

    $options['parameters']['page_variant']['type'] = 'entity:page_variant';

    $options['_admin_route'] = FALSE;

    $this->buildLayoutRoutes($collection, $this->getPluginDefinition(), $path, [], [], $options, '', 'page_variant');
  }

  /**
   * {@inheritdoc}
   */
  public function deriveContextsFromRoute($value, $definition, $name, array $defaults) {
    // Try to load from defaults.
    $entity = $this->extractEntityFromRoute($value, $defaults);

    // Otherwise try the tempstore.
    if (!$entity) {
      $entity = $this->tempstore->get('page_manager.layout_builder')->get($value);
    }

    return [
      'entity' => EntityContext::fromEntity($entity),
    ];
  }

  /**
   * {@inheritdoc}
   */
  private function extractEntityFromRoute($value, array $defaults) {
    if (!empty($value)) {
      return PageVariant::load($value);
    }

    return PageVariant::load($defaults['page_variant']);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getPageVariant()->label();
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $page_variant = $this->getPageVariant();
    return $page_variant->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIf($this->isLayoutBuilderEnabled())->addCacheableDependency($this);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(RefinableCacheableDependencyInterface $cacheability) {
    return $this->isLayoutBuilderEnabled();
  }

  /**
   * Determines if Layout Builder is enabled.
   *
   * @return bool
   *   TRUE if Layout Builder is enabled, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function isLayoutBuilderEnabled() {
    $entity = $this->getContextValue('entity');
    return method_exists($entity, 'getVariantPlugin') && $entity->getVariantPlugin() instanceof LayoutBuilderDisplayVariant;
  }

  /**
   * {@inheritdoc}
   */
  public function getSectionListFromId($id) {
    // @todo
    // This is deprecated and can be removed before Drupal 9.0.0.
  }

  /**
   * {@inheritdoc}
   */
  public function extractIdFromRoute($value, $definition, $name, array $defaults) {
    // @todo
    // This is deprecated and can be removed before Drupal 9.0.0.
  }

  /**
   * {@inheritdoc}
   */
  public function getContextsDuringPreview() {
    $contexts = $this->getPageVariant()->getContexts() + $this->getPageVariant()->getStaticContexts();

    foreach ($contexts as $name => $context) {
      if (!$context->hasContextValue()) {
        $data_type = $context->getContextDefinition()->getDataType();
        if (strpos((string) $data_type, 'entity:') === 0) {
          list(, $entity_type_id) = explode(':', $data_type, 2);

          $bundle = $entity_type_id;
          if ($this->entityTypeManager->getDefinition($entity_type_id)->hasKey('bundle')) {
            $bundle = key($this->entityTypeBundleInfo->getBundleInfo($entity_type_id));
          }

          $sample = $this->sampleEntityGenerator->get($entity_type_id, $bundle);
          $contexts[$name] = Context::createFromContext($context, $sample);
        }
      }
    }

    return $contexts;
  }

}

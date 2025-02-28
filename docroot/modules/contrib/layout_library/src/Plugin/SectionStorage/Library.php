<?php

namespace Drupal\layout_library\Plugin\SectionStorage;

use Drupal\Component\Plugin\Context\ContextInterface as ComponentContextInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\field_ui\FieldUI;
use Drupal\layout_builder\Entity\LayoutBuilderSampleEntityGenerator;
use Drupal\layout_builder\Plugin\SectionStorage\SectionStorageBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines a class for library based layout storage.
 *
 * This plugin, ultimately, requires the 'layout' context (i.e., a context that
 * wraps a layout entity). However, that context is normally derived from
 * another entity, which has a reference to a layout entity in its
 * layout_selection field.
 *
 * @SectionStorage(
 *   id = "layout_library",
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", required = FALSE),
 *     "layout" = @ContextDefinition("entity:layout", required = FALSE),
 *     "view_mode" = @ContextDefinition("string", required = FALSE),
 *   },
 * )
 */
class Library extends SectionStorageBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Sample entity generation.
   *
   * @var \Drupal\layout_builder\Entity\LayoutBuilderSampleEntityGenerator
   */
  protected $sampleEntityGenerator;

  /**
   * Constructs a new Library object.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   ID.
   * @param mixed $plugin_definition
   *   Definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\layout_builder\Entity\LayoutBuilderSampleEntityGenerator $sampleEntityGenerator
   *   Sample entity generator.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, LayoutBuilderSampleEntityGenerator $sampleEntityGenerator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->sampleEntityGenerator = $sampleEntityGenerator;
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
      $container->get('layout_builder.sample_entity_generator')
    );
  }

  /**
   * Gets the layout.
   *
   * @return \Drupal\layout_builder\SectionListInterface|\Drupal\layout_library\Entity\Layout
   *   Layout.
   */
  protected function getLayout() {
    return $this->getSectionList();
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageId() {
    return $this->getLayout()->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getSectionListFromId($id) {
    @trigger_error('\Drupal\layout_builder\SectionStorageInterface::getSectionListFromId() is deprecated in drupal:8.7.0 and will be removed before drupal:9.0.0. The section list should be derived from context. See https://www.drupal.org/node/3016262', E_USER_DEPRECATED);
    if ($layout = $this->entityTypeManager->getStorage('layout')->load($id)) {
      return $layout;
    }
    throw new \InvalidArgumentException(sprintf('The "%s" ID for the "%s" section storage type is invalid', $id, $this->getStorageType()));
  }

  /**
   * {@inheritdoc}
   */
  public function buildRoutes(RouteCollection $collection) {
    foreach ($this->getEntityTypes() as $entity_type_id => $entity_type) {
      // Try to get the route from the current collection.
      if (!$entity_route = $collection->get($entity_type->get('field_ui_base_route'))) {
        continue;
      }
      // Add a layout-library URL off the tail of each manage display.
      $path = $entity_route->getPath() . '/layout-library/{layout}';

      $defaults = [];
      $defaults['entity_type_id'] = $entity_type_id;
      // If the entity type has no bundles and it doesn't use {bundle} in its
      // admin path, use the entity type.
      if (strpos($path, '{bundle}') === FALSE) {
        if (!$entity_type->hasKey('bundle')) {
          $defaults['bundle'] = $entity_type_id;
        }
        else {
          $defaults['bundle_key'] = $entity_type->getBundleEntityType();
        }
      }

      $requirements = [];
      $requirements['_field_ui_view_mode_access'] = 'administer ' . $entity_type_id . ' display';

      $options = $entity_route->getOptions();
      $options['_admin_route'] = FALSE;
      $options['parameters']['layout']['type'] = 'entity:layout';

      $this->buildLayoutRoutes($collection, $this->getPluginDefinition(), $path, $defaults, $requirements, $options, $entity_type_id, 'layout');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl() {
    return Url::fromRoute('entity.layout.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutBuilderUrl($rel = 'view') {
    return Url::fromRoute("layout_builder.{$this->getStorageType()}.{$this->getLayout()->getTargetEntityType()}.$rel", $this->getRouteParameters());
  }

  /**
   * {@inheritdoc}
   */
  protected function getRouteParameters() {
    $layout = $this->getLayout();
    $route_parameters = FieldUI::getRouteBundleParameter($this->entityTypeManager->getDefinition($layout->getTargetEntityType()), $layout->getTargetBundle());
    $route_parameters['layout'] = $this->getLayout()->id();
    return $route_parameters;
  }

  /**
   * Returns an array of relevant entity types.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   An array of entity types.
   */
  protected function getEntityTypes() {
    return array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $entity_type) {
      return $entity_type->entityClassImplements(FieldableEntityInterface::class) && $entity_type->hasViewBuilderClass() && $entity_type->get('field_ui_base_route');
    });
  }

  /**
   * {@inheritdoc}
   */
  public function extractIdFromRoute($value, $definition, $name, array $defaults) {
    @trigger_error('\Drupal\layout_builder\SectionStorageInterface::extractIdFromRoute() is deprecated in drupal:8.7.0 and will be removed before drupal:9.0.0. \Drupal\layout_builder\SectionStorageInterface::deriveContextsFromRoute() should be used instead. See https://www.drupal.org/node/3016262', E_USER_DEPRECATED);
    return $value ?: $defaults['layout'];
  }

  /**
   * {@inheritdoc}
   */
  public function getContextsDuringPreview() {
    $contexts = parent::getContextsDuringPreview();

    $display = $this->getLayout();
    $entity = $this->sampleEntityGenerator->get($display->getTargetEntityType(), $display->getTargetBundle());
    $context_label = new TranslatableMarkup('@entity being viewed', ['@entity' => $entity->getEntityType()->getLabel()]);

    $contexts['layout_builder.entity'] = EntityContext::fromEntity($entity, $context_label);
    return $contexts;
  }

  /**
   * Extracts an entity from the route values.
   *
   * @param mixed $value
   *   The raw value from the route.
   * @param array $defaults
   *   The route defaults array.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity for the route, or NULL if none exist.
   */
  protected function extractEntityFromRoute($value, array $defaults) {
    return $this->entityTypeManager->getStorage('layout')->load($value ?: $defaults['layout']);
  }

  /**
   * {@inheritdoc}
   */
  public function deriveContextsFromRoute($value, $definition, $name, array $defaults) {
    $contexts = [];

    if ($entity = $this->extractEntityFromRoute($value, $defaults)) {
      $contexts['layout'] = EntityContext::fromEntity($entity);
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getLayout()->label();
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    return $this->getLayout()->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(RefinableCacheableDependencyInterface $cacheability) {
    // Since the 'layout' context must be marked optional, ensure that it is set
    // before proceeding.
    $is_library_enabled = FALSE;
    $values = $this->getContextValues();

    if (!is_null($values['layout'])) {
      $cacheability->addCacheableDependency($values['layout']);

      $entity = $values['layout']->getTargetEntityType();
      $bundle = $values['layout']->getTargetBundle();
      $view_mode = $values['view_mode'];
      $entity_view_display = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->load($entity . '.' . $bundle . '.' . $view_mode);

      if ($entity_view_display) {
        $is_library_enabled = $entity_view_display->getThirdPartySetting('layout_library', 'enable');
      }
    }
    return $this->getSectionList() && $is_library_enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function setContext($name, ComponentContextInterface $context) {
    $value = $context->getContextValue();
    // This cannot be done with constraints because the context handler does not
    // currently validate optional context definitions.
    if ($name === 'entity' && $value instanceof FieldableEntityInterface && $value->hasField('layout_selection') && $value->get('layout_selection')->entity) {
      $name = 'layout';
      $context = EntityContext::fromEntity($value->get('layout_selection')->entity);
    }
    parent::setContext($name, $context);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSectionList() {
    return $this->getContextValue('layout');
  }

}

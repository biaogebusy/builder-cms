<?php

namespace Drupal\page_manager\Entity;

use Drupal\Component\Plugin\Context\ContextInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\page_manager\Context\ContextDefinitionFactory;
use Drupal\page_manager\Event\PageManagerContextEvent;
use Drupal\page_manager\Event\PageManagerEvents;
use Drupal\page_manager\PageInterface;
use Drupal\page_manager\PageVariantInterface;

/**
 * Defines a Page entity class.
 *
 * @ConfigEntityType(
 *   id = "page",
 *   label = @Translation("Page"),
 *   handlers = {
 *     "access" = "Drupal\page_manager\Entity\PageAccess",
 *   },
 *   admin_permission = "administer pages",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "use_admin_theme",
 *     "path",
 *     "access_logic",
 *     "access_conditions",
 *     "parameters",
 *     "menu_type",
 *     "menu_settings",
 *   },
 * )
 */
class Page extends ConfigEntityBase implements PageInterface {

  /**
   * The ID of the page entity.
   *
   * @var string
   */
  protected $id;

  /**
   * The label of the page entity.
   *
   * @var string
   */
  protected $label;

  /**
   * The description of the page entity.
   *
   * @var string
   */
  protected $description;

  /**
   * The path of the page entity.
   *
   * @var string
   */
  protected $path;

  /**
   * The page variant entities.
   *
   * @var \Drupal\page_manager\PageVariantInterface[]
   */
  protected $variants;

  /**
   * An array of collected contexts.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]
   */
  protected $contexts = [];

  /**
   * The configuration of access conditions.
   *
   * @var array
   */
  protected $access_conditions = [];

  /**
   * Tracks the logic used to compute access, either 'and' or 'or'.
   *
   * @var string
   */
  protected $access_logic = 'and';

  /**
   * The plugin collection that holds the access conditions.
   *
   * @var \Drupal\Component\Plugin\LazyPluginCollection
   */
  protected $accessConditionCollection;

  /**
   * Indicates if this page should be displayed in the admin theme.
   *
   * @var bool
   */
  protected $use_admin_theme;

  /**
   * The menu type.
   *
   * @var string
   */
  protected $menu_type = 'none';

  /**
   * The menu settings.
   *
   * @var array
   */
  protected $menu_settings = [];

  /**
   * Parameter context configuration.
   *
   * An associative array keyed by parameter name, which contains associative
   * arrays with the following keys:
   * - machine_name: Machine-readable context name.
   * - label: Human-readable context name.
   * - type: Context type.
   * - optional: Whether the parameter is optional.
   *
   * @var array[]
   */
  protected $parameters = [];

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    return $this->path ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function usesAdminTheme() {
    return $this->use_admin_theme ?? strpos((string) $this->getPath(), '/admin/') === 0;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    static::routeBuilder()->setRebuildNeeded();
    $this->invalidateMenu();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    static::routeBuilder()->setRebuildNeeded();
    /** @var \Drupal\page_manager\Entity\Page $entity */
    foreach ($entities as $entity) {
      $entity->invalidateMenu();
    }
  }

  /**
   * Wraps the route builder.
   *
   * @return \Drupal\Core\Routing\RouteBuilderInterface
   *   An object for state storage.
   */
  protected static function routeBuilder() {
    return \Drupal::service('router.builder');
  }

  /**
   * Wraps the entity storage for page variants.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   Page variant entity storage.
   */
  protected function variantStorage() {
    return \Drupal::service('entity_type.manager')->getStorage('page_variant');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'access_conditions' => $this->getAccessConditions(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessConditions() {
    if (!$this->accessConditionCollection) {
      $this->accessConditionCollection = new ConditionPluginCollection(\Drupal::service('plugin.manager.condition'), $this->get('access_conditions'));
    }
    return $this->accessConditionCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function addAccessCondition(array $configuration) {
    $configuration['uuid'] = $this->uuidGenerator()->generate();
    $this->getAccessConditions()->addInstanceId($configuration['uuid'], $configuration);
    return $configuration['uuid'];
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessCondition($condition_id) {
    return $this->getAccessConditions()->get($condition_id);
  }

  /**
   * {@inheritdoc}
   */
  public function removeAccessCondition($condition_id) {
    $this->getAccessConditions()->removeInstanceId($condition_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessLogic() {
    return $this->access_logic;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters() {
    $names = $this->getParameterNames();
    if ($names) {
      return array_intersect_key($this->parameters, array_flip($names));
    }
    return [];
  }

  /**
   * Defaults for new parameters.
   *
   * @return array
   *   An array of default parameter values.
   */
  public function parameterDefaults() {
    return [
      'type' => '',
      'machine_name' => '',
      'label' => '',
      'optional' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getParameter($name) {
    if ($this->hasParameter($name)) {
      return $this->parameters[$name];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasParameter($name) {
    return isset($this->parameters[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function setParameter($name, $type, $label = '', $optional = FALSE) {
    $this->parameters[$name] = [
      'machine_name' => $name,
      'type' => $type,
      'label' => $label,
      'optional' => $optional,
    ];
    // Reset contexts when a parameter is added or changed.
    $this->contexts = [];
    // Reset the contexts of every variant.
    foreach ($this->getVariants() as $page_variant) {
      $page_variant->resetCollectedContexts();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeParameter($name) {
    unset($this->parameters[$name]);
    // Reset contexts when a parameter is removed.
    $this->contexts = [];
    // Reset the contexts of every variant.
    foreach ($this->getVariants() as $page_variant) {
      $page_variant->resetCollectedContexts();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameterNames() {
    if ($this->getPath() && preg_match_all('|\{(\w+)\}|', (string) $this->getPath(), $matches)) {
      return $matches[1];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $this->filterParameters();
  }

  /**
   * Filters the parameters to remove any without a valid type.
   *
   * @return $this
   */
  protected function filterParameters() {
    $names = $this->getParameterNames();
    foreach ($this->get('parameters') as $name => $parameter) {
      // Remove parameters without any type, or which are no longer valid.
      if (empty($parameter['type']) || !in_array($name, $names)) {
        $this->removeParameter($name);
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addContext($name, ContextInterface $value) {
    $this->contexts[$name] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts() {
    if (!$this->contexts) {
      $this->eventDispatcher()->dispatch(new PageManagerContextEvent($this), PageManagerEvents::PAGE_CONTEXT);
      foreach ($this->getParameters() as $machine_name => $configuration) {
        // Parameters can be updated in the UI, so unless it's a global context
        // we'll need to rely on the current settings in the tempstore instead
        // of the ones cached in the router.
        if (!isset($global_contexts[$machine_name])) {
          // First time through, parameters will not be defined by the route.
          if (!isset($this->contexts[$machine_name])) {
            $cacheability = new CacheableMetadata();
            $cacheability->setCacheContexts(['route']);

            $context_definition = ContextDefinitionFactory::create($configuration['type'])
              ->setLabel($configuration['label'])
              ->setRequired(empty($configuration['optional']));
            $context = new Context($context_definition);
            $context->addCacheableDependency($cacheability);
            $this->contexts[$machine_name] = $context;
          }
          else {
            $this->contexts[$machine_name]->getContextDefinition()->setDataType($configuration['type']);
            if (!empty($configuration['label'])) {
              $this->contexts[$machine_name]->getContextDefinition()->setLabel($configuration['label']);
            }
          }
        }
      }
    }
    return $this->contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuType() {
    return $this->menu_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuSettings() {
    return $this->menu_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenu() {
    return $this->createMenu($this->getMenuType(), $this->getMenuSettings());
  }

  /**
   * Instantiates a menu type plugin.
   *
   * @param string $menu_type
   *   The menu type plugin ID, or 'none'.
   * @param array $menu_settings
   *   The plugin configuration.
   *
   * @return \Drupal\page_manager\Plugin\PageManagerMenuInterface|null
   *   The menu type plugin, or NULL if the page has no menu entry or the
   *   configured plugin is no longer available.
   */
  protected function createMenu($menu_type, array $menu_settings = []) {
    if (empty($menu_type) || $menu_type === 'none') {
      return NULL;
    }

    try {
      return static::menuManager()->createInstance($menu_type, $menu_settings);
    }
    catch (PluginNotFoundException $e) {
      // The module providing the menu type has been uninstalled. There is
      // nothing to invalidate and nothing to render, so treat it as no menu
      // entry rather than throwing on every save of the page.
      return NULL;
    }
  }

  /**
   * Invalidates the menu entries this page provides.
   *
   * Called after the page is saved or deleted. When the menu type itself has
   * changed the previously configured type is invalidated as well, otherwise
   * the menu link or local task the page used to provide would be left behind.
   */
  protected function invalidateMenu() {
    $menu_types = [$this->getMenuType() => $this->getMenuSettings()];

    // \Drupal\Core\Entity\EntityInterface::getOriginal() was only added in
    // Drupal 11.2, so fall back to the property on older supported core.
    $original = method_exists($this, 'getOriginal') ? $this->getOriginal() : ($this->original ?? NULL);
    if ($original instanceof PageInterface && $original->getMenuType() !== $this->getMenuType()) {
      $menu_types[$original->getMenuType()] = $original->getMenuSettings();
    }

    foreach ($menu_types as $menu_type => $menu_settings) {
      if ($menu = $this->createMenu($menu_type, $menu_settings)) {
        $menu->invalidate();
      }
    }
  }

  /**
   * Wraps the menu type plugin manager.
   *
   * @return \Drupal\page_manager\Plugin\PageManagerMenuManager
   *   The menu type plugin manager.
   */
  protected static function menuManager() {
    return \Drupal::service('plugin.manager.page_manager_menu');
  }

  /**
   * {@inheritdoc}
   */
  public function addVariant(PageVariantInterface $variant) {
    // If variants hasn't been initialized, we initialize it before adding the
    // new variant.
    if ($this->variants === NULL) {
      $this->getVariants();
    }
    $this->variants[$variant->id()] = $variant;
    $this->sortVariants();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariant($variant_id) {
    $variants = $this->getVariants();
    if (!isset($variants[$variant_id])) {
      throw new \UnexpectedValueException('The requested variant does not exist or is not associated with this page');
    }
    return $variants[$variant_id];
  }

  /**
   * {@inheritdoc}
   */
  public function removeVariant($variant_id) {
    $this->getVariant($variant_id)->delete();
    unset($this->variants[$variant_id]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVariants() {
    if (!isset($this->variants)) {
      $this->variants = [];
      /** @var \Drupal\page_manager\PageVariantInterface $variant */
      foreach ($this->variantStorage()->loadByProperties(['page' => $this->id()]) as $variant) {
        $this->variants[$variant->id()] = $variant;
      }
      $this->sortVariants();
    }
    return $this->variants;
  }

  /**
   * Sort variants.
   */
  protected function sortVariants() {
    if (isset($this->variants)) {
      // Suppress errors because of https://bugs.php.net/bug.php?id=50688.
      @uasort($this->variants, [$this, 'variantSortHelper']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function variantSortHelper($a, $b) {
    $a_weight = $a->getWeight();
    $b_weight = $b->getWeight();
    if ($a_weight == $b_weight) {
      return 0;
    }

    return ($a_weight < $b_weight) ? -1 : 1;
  }

  /**
   * Wraps the event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  protected function eventDispatcher() {
    return \Drupal::service('event_dispatcher');
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    $vars = parent::__sleep();

    // Gathered contexts objects should not be serialized.
    if (($key = array_search('contexts', $vars)) !== FALSE) {
      unset($vars[$key]);
    }

    return $vars;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove this as part of https://www.drupal.org/node/2696683.
   */
  protected function urlRouteParameters($rel) {
    if ($rel == 'edit-form') {
      $uri_route_parameters = [];
      $uri_route_parameters['machine_name'] = $this->id();
      $uri_route_parameters['step'] = 'general';
      return $uri_route_parameters;
    }

    return parent::urlRouteParameters($rel);
  }

}

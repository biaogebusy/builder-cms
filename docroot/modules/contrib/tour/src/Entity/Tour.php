<?php

namespace Drupal\tour\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tour\Form\TourCloneForm;
use Drupal\tour\Form\TourDeleteForm;
use Drupal\tour\Form\TourForm;
use Drupal\tour\Form\TourTipsListForm;
use Drupal\tour\TipPluginInterface;
use Drupal\tour\TipsPluginCollection;
use Drupal\tour\TourAccessControlHandler;
use Drupal\tour\TourInterface;
use Drupal\tour\TourListBuilder;
use Drupal\tour\TourViewBuilder;

/**
 * Defines the configured tour entity.
 */
#[ConfigEntityType(
  id: 'tour',
  label: new TranslatableMarkup('Tour'),
  label_collection: new TranslatableMarkup('Tours'),
  label_singular: new TranslatableMarkup('tour'),
  label_plural: new TranslatableMarkup('tours'),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'view_builder' => TourViewBuilder::class,
    'access' => TourAccessControlHandler::class,
    'list_builder' => TourListBuilder::class,
    'form' => [
      'default' => TourForm::class,
      'add' => TourForm::class,
      'edit' => TourForm::class,
      'edit_tips' => TourTipsListForm::class,
      'delete' => TourDeleteForm::class,
      'clone' => TourCloneForm::class,
    ],
  ],
  links: [
    'edit-form' => '/admin/config/user-interface/tour/manage/{tour}',
    'delete-form' => '/admin/config/user-interface/tour/manage/{tour}/delete',
    'clone-form' => '/admin/config/user-interface/tour/manage/{tour}/clone',
  ],
  admin_permission: 'administer tour',
  label_count: [
    'singular' => '@count tour',
    'plural' => '@count tours',
  ],
  config_export: [
    'id',
    'label',
    'routes',
    'tips',
  ],
  lookup_keys: [
    'routes.*.route_name',
  ],
)]
class Tour extends ConfigEntityBase implements TourInterface {

  /**
   * The name (plugin ID) of the tour.
   *
   * @var string|null
   */
  protected string|null $id;

  /**
   * The label of the tour.
   *
   * @var string
   */
  protected string $label;

  /**
   * The routes on which this tour should be displayed.
   *
   * @var array
   */
  protected $routes = [];

  /**
   * The modules needed by this tour.
   */
  protected ?string $module = NULL;

  /**
   * The routes on which this tour should be displayed, keyed by route id.
   *
   * @var array
   */
  protected $keyedRoutes;

  /**
   * Holds the collection of tips that are attached to this tour.
   *
   * @var \Drupal\tour\TipsPluginCollection
   */
  protected TipsPluginCollection $tipsCollection;

  /**
   * The array of plugin config.
   *
   * Only used for export and to populate the $tipsCollection.
   *
   * @var array
   */
  protected $tips = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    $this->tipsCollection = new TipsPluginCollection(\Drupal::service('plugin.manager.tour.tip'), $this->tips);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(): array {
    return $this->routes;
  }

  /**
   * {@inheritdoc}
   */
  public function getModule(): string {
    if (is_null($this->module)) {
      return '';
    }
    return $this->module;
  }

  /**
   * {@inheritdoc}
   */
  public function getTip(string $id): TipPluginInterface {
    return $this->tipsCollection->get($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTips(): array {
    $tips = [];
    foreach ($this->tips as $id => $tip) {
      $tips[$id] = $this->getTip($id);
    }

    \Drupal::moduleHandler()->alter('tour_tips', $tips, $this);
    return $tips;
  }

  /**
   * {@inheritdoc}
   */
  public function hasMatchingRoute(string $route_name, array $route_params): bool {
    if (!isset($this->keyedRoutes)) {
      $this->keyedRoutes = [];
      foreach ($this->getRoutes() as $route) {
        // There may be multiple routes with the same route name, e.g.
        // multiple entity.node.canonical entries with different node ID's:
        $this->keyedRoutes[$route['route_name']][] = $route['route_params'] ?? [];
      }
    }
    if (!isset($this->keyedRoutes[$route_name])) {
      // We don't know about the given $route_name.
      return FALSE;
    }
    foreach ($this->keyedRoutes[$route_name] as $routeMatch) {
      if (empty($routeMatch)) {
        // No route_params given, so we don't need to worry about route params,
        // the route name is enough.
        return TRUE;
      }
      foreach ($routeMatch as $key => $value) {
        if (!empty($route_params[$key]) && $route_params[$key] === $value) {
          // Return true if any route matches:
          return TRUE;
        }
      }
    }
    // None of the routes matched:
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    foreach ($this->tipsCollection as $instance) {
      $definition = $instance->getPluginDefinition();
      $this->addDependency('module', $definition['provider']);
    }

    // When the TourForm is saved set the dependencies based on user input.
    $modules_array = explode(',', str_replace(' ', '', $this->getModule()));
    if (is_array($modules_array) && isset($this->form_id)) {
      asort($modules_array);
      $this->dependencies['enforced']['module'] = $modules_array;
    }

    return $this;
  }

}

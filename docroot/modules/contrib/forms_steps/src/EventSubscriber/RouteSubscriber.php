<?php

declare(strict_types=1);

namespace Drupal\forms_steps\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\forms_steps\Entity\FormsSteps;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Builds up the routes of all forms steps. (based on views RouteSubscriber)
 *
 * The general idea is to execute first all alter hooks to determine which
 * routes are overridden by forms steps. This information is used to determine
 * which forms steps have to be added by forms steps in the dynamic event.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Stores a list of view,display IDs which haven't be used in the alter event.
   *
   * @var array
   */
  protected array $viewsDisplayPairs;

  /**
   * The forms steps storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $formsStepsStorage;

  /**
   * The state key value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Stores an array of route names keyed by FormsSteps.Step.
   *
   * @var array
   */
  protected array $formsStepsRouteNames = [];

  /**
   * EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a \Drupal\forms_steps\EventSubscriber\RouteSubscriber instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state key value store.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    StateInterface $state,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory
  ) {
    $this->formsStepsStorage = $entity_type_manager->getStorage(FormsSteps::ENTITY_TYPE);
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Returns a set of route objects.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   A route collection.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function routes(): RouteCollection {
    $collection = new RouteCollection();
    $entity_ids = $this->entityTypeManager
      ->getStorage(FormsSteps::ENTITY_TYPE)
      ->getQuery()
      ->accessCheck()
      ->execute();

    // Loads of all forms steps.
    $forms_steps = $this->entityTypeManager
      ->getStorage(FormsSteps::ENTITY_TYPE)
      ->loadMultiple($entity_ids);

    $route_options = [];
    if ($this->configFactory->get('node.settings')->get('use_admin_theme')) {
      $route_options['_admin_route'] = TRUE;
    }

    /** @var \Drupal\forms_steps\Entity\FormsSteps $form_steps */
    foreach ($forms_steps as $form_steps) {
      foreach ($form_steps->getSteps() as $step) {
        $route = new Route(
          $step->url() . '/{instance_id}',
          [
            '_controller' => '\Drupal\forms_steps\Controller\FormsStepsController::step',
            '_title' => $step->label(),
            'forms_steps' => $form_steps->id(),
            'step' => $step->id(),
            'instance_id' => '',
          ],
          [
            '_permission' => 'access content',
            'instance_id' => '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
          ],
          $route_options
        );

        $collection->add('forms_steps.' . $form_steps->id() . '.' . $step->id(), $route);
      }
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): RouteCollection {

    // @todo Implements alter on steps URL that has been overriden by route for.
    // example in url alias menu.
    return $collection;
  }

}

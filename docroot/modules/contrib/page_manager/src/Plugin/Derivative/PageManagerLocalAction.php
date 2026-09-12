<?php

namespace Drupal\page_manager\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local actions for Page manager.
 */
class PageManagerLocalAction extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a PageManagerLocalAction.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->getApplicablePages() as $page_id) {
      /** @var \Drupal\page_manager\PageInterface $page */
      $page = $this->entityTypeManager->getStorage('page')->load($page_id);
      if (!$page || !$page->status()) {
        continue;
      }

      $menu_settings = $page->getMenuSettings();
      $path = $page->getPath();

      try {
        $route_name = Url::fromUri('internal:' . $path)->getRouteName();
      }
      catch (\UnexpectedValueException $e) {
        continue;
      }

      // The action is shown on the parent of the page it points at.
      // @todo Find out how to find both the root and parent tab.
      $split = explode('/', $path);
      array_pop($split);
      $parent_path = implode('/', $split);

      try {
        $parent_route_name = Url::fromUri('internal:' . $parent_path)->getRouteName();
      }
      catch (\UnexpectedValueException $e) {
        continue;
      }

      $plugin_id = 'page_manager.page_action_' . $page_id;

      $this->derivatives[$plugin_id] = [
        'route_name' => $route_name,
        'title' => $menu_settings['title'] ?? '',
        'weight' => (int) ($menu_settings['weight'] ?? 0),
        'appears_on' => [
          $parent_route_name,
        ],
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

  /**
   * Gets the page ids of pages that provide a local action.
   *
   * @return string[]
   *   The page IDs.
   */
  public function getApplicablePages() {
    return $this->entityTypeManager->getStorage('page')->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_type', 'menu_action')
      ->execute();
  }

}

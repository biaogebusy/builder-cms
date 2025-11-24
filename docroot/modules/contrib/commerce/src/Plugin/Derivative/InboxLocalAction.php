<?php

namespace Drupal\commerce\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Commerce inbox action link deriver.
 */
class InboxLocalAction extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new InboxLocalAction object.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree
   *   The menu tree.
   */
  public function __construct(protected MenuLinkTreeInterface $menuTree) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('menu.link_tree')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!Settings::get('commerce_dashboard_show_toolbar_link', TRUE)) {
      return [];
    }

    $this->derivatives = [];
    $params = new MenuTreeParameters();
    $params->setRoot('commerce.admin_commerce')
      ->excludeRoot()
      ->setTopLevelOnly()
      ->onlyEnabledLinks();
    $tree = $this->menuTree->load('admin', $params);

    if (!$tree) {
      return $this->derivatives;
    }

    $routes = [];
    foreach ($tree as $element) {
      $routes[] = $element->link->getRouteName();
    }

    $this->derivatives['commerce.inbox_message'] = [
      'route_name' => 'commerce.admin_commerce',
      'appears_on' => $routes,
    ];

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}

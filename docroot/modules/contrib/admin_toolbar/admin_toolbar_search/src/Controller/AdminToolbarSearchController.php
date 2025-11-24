<?php

namespace Drupal\admin_toolbar_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class AdminToolbarSearchController to the search functionality.
 *
 * @package Drupal\admin_toolbar_search\Controller
 */
class AdminToolbarSearchController extends ControllerBase {

  /**
   * The search links service.
   *
   * @var \Drupal\admin_toolbar_search\SearchLinks
   */
  protected $links;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->links = $container->get('admin_toolbar_search.search_links');
    return $instance;
  }

  /**
   * Return additional search links.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response with the search links.
   */
  public function search() {
    return new JsonResponse($this->links->getLinks());
  }

}

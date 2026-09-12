<?php

namespace Drupal\views_add_button\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\views_add_button\ViewsAddButtonManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ViewsAddButtonController.
 *
 * Provides the route and API controller for views_add_button.
 *
 * @package Drupal\views_add_button\Controller
 */
class ViewsAddButtonController extends ControllerBase {

  /**
   * ViewsAddButtonController constructor.
   *
   * @param \Drupal\views_add_button\ViewsAddButtonManager $viewsAddButtonManager
   *   The plugin manager object.
   */
  public function __construct(
    /**
     * The plugin manager.
     */
    protected ViewsAddButtonManager $viewsAddButtonManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /*
     * Use the service container to instantiate
     * a new instance of our controller.
     */
    return new static($container->get('plugin.manager.views_add_button'));
  }

}

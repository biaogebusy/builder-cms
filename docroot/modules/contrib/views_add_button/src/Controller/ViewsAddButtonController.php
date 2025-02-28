<?php

namespace Drupal\views_add_button\Controller;

use Drupal\views_add_button\ViewsAddButtonManager;
use Drupal\Core\Controller\ControllerBase;
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
   * The plugin manager.
   *
   * @var \Drupal\views_add_button\ViewsAddButtonManager
   */
  protected $ViewsAddButtonManager;

  /**
   * ViewsAddButtonController constructor.
   *
   * @param \Drupal\views_add_button\ViewsAddButtonManager $plugin_manager
   *   The plugin manager object.
   */
  public function __construct(ViewsAddButtonManager $plugin_manager) {
    $this->ViewsAddButtonManager = $plugin_manager;
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

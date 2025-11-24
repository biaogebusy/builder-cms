<?php

namespace Drupal\profile\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\profile\Entity\ProfileType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Modifies the local action to add a destination.
 */
class ProfileLocalAction extends LocalActionDefault {

  use StringTranslationTrait;

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  private $redirectDestination;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->redirectDestination = $container->get('redirect.destination');
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = parent::getOptions($route_match);
    // Append the current path as destination to the query string.
    $options['query']['destination'] = $this->redirectDestination->get();
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL) {
    $route_params = $this->getRouteParameters($this->routeMatch);
    $profile_type = $profile_type = ProfileType::load($route_params['profile_type']);
    return $profile_type ?
      $this->t('Add @profile_type', ['@profile_type' => $profile_type->getDisplayLabel() ?: $profile_type->label()])
      : $this->t('Add profile');
  }

}

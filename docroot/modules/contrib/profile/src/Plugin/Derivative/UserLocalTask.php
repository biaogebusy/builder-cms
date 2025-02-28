<?php

namespace Drupal\profile\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a user page local task for each profile type.
 */
class UserLocalTask extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new self();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    // Starting weight for ordering the local tasks.
    $weight = 10;
    /** @var \Drupal\profile\Entity\ProfileTypeInterface[] $profile_types */
    $profile_types = $this->entityTypeManager->getStorage('profile_type')->loadMultiple();
    foreach ($profile_types as $profile_type_id => $profile_type) {
      if ($profile_type->allowsMultiple()) {
        $route_name = 'profile.user_page.multiple';
      }
      else {
        $route_name = 'profile.user_page.single';
      }

      $this->derivatives[$profile_type_id] = [
        'title' => $profile_type->getDisplayLabel() ?: $profile_type->label(),
        'route_name' => $route_name,
        'base_route' => 'entity.user.canonical',
        'route_parameters' => ['profile_type' => $profile_type_id],
        'weight' => ++$weight,
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}

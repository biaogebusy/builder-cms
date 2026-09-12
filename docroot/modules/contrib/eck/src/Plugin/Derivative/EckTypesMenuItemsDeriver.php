<?php

namespace Drupal\eck\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides menu links for the different settings entity types & bundles.
 */
class EckTypesMenuItemsDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $basePluginId) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->routeProvider = $container->get('router.route_provider');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $entityTypes = $this->entityTypeManager
      ->getStorage('eck_entity_type')
      ->loadMultiple();

    foreach ($entityTypes as $entityType) {
      $id = sprintf('eck.entity_type.%s', $entityType->id());
      $this->derivatives[$id] = [
        'title' => $entityType->label(),
        'route_name' => 'entity.eck_entity_type.edit_form',
        'route_parameters' => [
          'eck_entity_type' => $entityType->id(),
        ],
        'parent' => 'eck.entity_type.admin.structure.settings',
        'id' => $id,
      ] + $base_plugin_definition;

      $parentId = implode(':', [$base_plugin_definition['id'], $id]);
      $bundleType = sprintf('%s_type', $entityType->id());

      if (!$this->entityTypeManager->hasDefinition($bundleType)) {
        continue;
      }

      $bundles = $this->entityTypeManager
        ->getStorage($bundleType)
        ->loadMultiple();

      foreach ($bundles as $bundle) {
        $id = sprintf('eck.entity_type.%s.%s', $entityType->id(), $bundle->id());
        $this->derivatives[$id] = [
          'title' => $bundle->label(),
          'route_name' => sprintf('entity.%s.edit_form', $bundleType),
          'route_parameters' => [
            $bundleType => $bundle->id(),
          ],
          'parent' => $parentId,
          'id' => $id,
        ] + $base_plugin_definition;

        $bundleParentId = implode(':', [$base_plugin_definition['id'], $id]);
        if ($this->moduleHandler->moduleExists('field_ui')) {
          $this->derivatives['entity.' . $entityType->id() . '.field_ui_fields.' . $bundle->id()] = [
            'title' => $this->t('Manage fields'),
            'route_name' => 'entity.' . $entityType->id() . '.field_ui_fields',
            'parent' => $bundleParentId,
            'route_parameters' => [
              $bundleType => $bundle->id(),
            ],
            'weight' => 1,
          ] + $base_plugin_definition;
          $this->derivatives['entity.entity_form_display.' . $entityType->id() . '.default.' . $bundle->id()] = [
            'title' => $this->t('Manage form display'),
            'route_name' => 'entity.entity_form_display.' . $entityType->id() . '.default',
            'parent' => $bundleParentId,
            'route_parameters' => [
              $bundleType => $bundle->id(),
            ],
            'weight' => 2,
          ] + $base_plugin_definition;
          $this->derivatives['entity.entity_view_display.' . $entityType->id() . '.default.' . $bundle->id()] = [
            'title' => $this->t('Manage display'),
            'route_name' => 'entity.entity_view_display.' . $entityType->id() . '.default',
            'parent' => $bundleParentId,
            'route_parameters' => [
              $bundleType => $bundle->id(),
            ],
            'weight' => 3,
          ] + $base_plugin_definition;
          if ($this->routeExists('entity.' . $entityType->id() . '.entity_permissions_form')) {
            $this->derivatives['entity.entity_permissions_form.' . $entityType->id() . '.default.' . $bundle->id()] = [
              'title' => $this->t('Manage permissions'),
              'route_name' => 'entity.' . $entityType->id() . '.entity_permissions_form',
              'parent' => $bundleParentId,
              'route_parameters' => [
                $bundleType => $bundle->id(),
              ],
              'weight' => 3,
            ] + $base_plugin_definition;
          }
        }
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Determine if a route exists by name.
   *
   * @param string $routeName
   *   The name of the route to check.
   *
   * @return bool
   *   Whether a route with that route name exists.
   */
  public function routeExists(string $routeName) {
    return (count($this->routeProvider->getRoutesByNames([$routeName])) === 1);
  }

}

<?php

namespace Drupal\eck\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\eck\Entity\EckEntityType;

/**
 * Provides local task definitions for all entity bundles.
 */
class EckEntityContentLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  private $basePluginDefinition;

  /**
   * The constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($basePluginDefinition) {
    $this->basePluginDefinition = $basePluginDefinition;
    $derivatives = [];

    /** @var \Drupal\eck\Entity\EckEntityType $type */
    foreach (EckEntityType::loadMultiple() as $type) {
      $entity_type = $type->id();
      $base_route = "entity.{$entity_type}.canonical";

      $derivative = $this->createDerivativeDefinition("entity.{$entity_type}.canonical", 1, 'View', $base_route);
      $derivatives["{$entity_type}.eck_canonical_tab"] = $derivative;

      $derivative = $this->createDerivativeDefinition("entity.{$entity_type}.edit_form", 2, 'Edit', $base_route);
      $derivatives["{$entity_type}.eck_edit_tab"] = $derivative;

      $derivative = $this->createDerivativeDefinition("entity.{$entity_type}.delete_form", 3, 'Delete', $base_route);
      $derivatives["{$entity_type}.eck_delete_tab"] = $derivative;
    }

    return $derivatives;
  }

  /**
   * Creates a derivative definition.
   *
   * @param string $routeName
   *   The route name.
   * @param int $weight
   *   The weight.
   * @param string $title
   *   The title.
   * @param string $base_route
   *   The base route.
   *
   * @return array
   *   The created derivative definition.
   */
  private function createDerivativeDefinition($routeName, $weight, $title, $base_route) {
    $derivative = [
      'route_name' => $routeName,
      'weight' => $weight,
      'title' => $this->t($title),
      'base_route' => $base_route,
    ] + $this->basePluginDefinition;
    return $derivative;
  }

}

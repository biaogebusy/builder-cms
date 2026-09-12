<?php

namespace Drupal\eck\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\eck\Entity\EckEntityType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all entity bundles.
 */
class EckEntityContentLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The base plugin definition.
   *
   * @var array
   */
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
      // Add local tasks for the entities.
      $entity_type = $type->id();
      $base_route = "entity.{$entity_type}.canonical";

      $derivative = $this->createDerivativeDefinition("entity.{$entity_type}.canonical", 1, $this->t('View'), $base_route);
      $derivatives["{$entity_type}.eck_canonical_tab"] = $derivative;

      if ($type->hasStandaloneUrl()) {
        $derivative = $this->createDerivativeDefinition("entity.{$entity_type}.edit_form", 2, $this->t('Edit'), $base_route);
        $derivatives["{$entity_type}.eck_edit_tab"] = $derivative;
      }
      else {
        $derivatives["{$entity_type}.eck_canonical_tab"]['title'] = $this->t('Edit');
      }

      if ($type->hasLinkTemplate('delete-form')) {
        $derivative = $this->createDerivativeDefinition("entity.{$entity_type}.delete_form", 3, $this->t('Delete'), $base_route);
        $derivatives["{$entity_type}.eck_delete_tab"] = $derivative;
      }

      // Add local tasks for the entity bundle.
      $entity_type = $type->id() . '_type';
      $base_route = "entity.{$entity_type}.edit_form";

      $derivative = $this->createDerivativeDefinition($base_route, 2, $this->t('Edit'), $base_route);
      $derivatives["{$entity_type}.eck_edit_tab"] = $derivative;
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
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $title
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
      'title' => $title,
      'base_route' => $base_route,
    ] + $this->basePluginDefinition;
    return $derivative;
  }

}

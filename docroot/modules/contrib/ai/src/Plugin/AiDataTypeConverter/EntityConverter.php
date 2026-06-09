<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiDataTypeConverter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiDataTypeConverter;
use Drupal\ai\Base\AiDataTypeConverterPluginBase;
use Drupal\ai\DataTypeConverter\AppliesResult;
use Drupal\ai\DataTypeConverter\AppliesResultInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ai_data_type_converter.
 */
#[AiDataTypeConverter(
  id: 'entity',
  label: new TranslatableMarkup('Entity'),
  description: new TranslatableMarkup('Upcast entity tokens from strings (i.e. "user:3", "node:1:fr" or "image_style:large") to entity objects.'),
)]
class EntityConverter extends AiDataTypeConverterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a FunctionCall plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToDataType(string $data_type): AppliesResultInterface {
    if (str_starts_with($data_type, 'entity:') || $data_type === 'entity') {
      return AppliesResult::applicable();
    }
    return AppliesResult::notApplicable('Converter does not apply to data type.');
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToValue(string $data_type, mixed $value): AppliesResultInterface {
    // Check if the value is a string and matches the expected format.
    if (!is_string($value)) {
      return AppliesResult::notApplicable('Value is not a string.');
    }
    // We expect strings formatted as "entity_type_id:id"
    // or "entity_type_id:id:langcode".
    $parts = explode(':', $value);
    if (count($parts) !== 2 && count($parts) !== 3) {
      return AppliesResult::invalidWithExamples(['<entity_type_id>:<id>', '<entity_type_id>:<id>:<langcode>'], $value);
    }

    [$entity_type_id, $id] = $parts;
    $langcode = count($parts) === 3 ? $parts[2] : NULL;

    if (str_starts_with($data_type, 'entity:')) {
      $definition_parts = explode(':', $data_type);
      if ($entity_type_id !== $definition_parts[1]) {
        return AppliesResult::invalid('Invalid entity type ID.');
      }
    }
    elseif ($data_type === 'entity') {
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        return AppliesResult::invalid('Invalid entity type ID.');
      }
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);

    if (!$entity) {
      return AppliesResult::invalid('Entity with provided ID does not exist.');
    }

    if ($langcode !== NULL && !$entity->hasTranslation($langcode)) {
      return AppliesResult::invalid('Entity does not have the specified translation.');
    }

    return AppliesResult::applicable();
  }

  /**
   * {@inheritdoc}
   */
  public function convert(string $data_type, mixed $value): mixed {
    $parts = explode(':', $value);
    [$entity_type_id, $id] = $parts;
    $langcode = count($parts) === 3 ? $parts[2] : NULL;

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    if ($langcode !== NULL) {
      return $entity->getTranslation($langcode);
    }
    return $entity;
  }

}

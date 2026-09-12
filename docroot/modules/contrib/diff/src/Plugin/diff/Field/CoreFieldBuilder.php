<?php

declare(strict_types=1);

namespace Drupal\diff\Plugin\diff\Field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\diff\Attribute\FieldDiffBuilder;
use Drupal\diff\DiffEntityParser;
use Drupal\diff\FieldDiffBuilderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin to diff core field types.
 */
#[FieldDiffBuilder(
  id: 'core_field_diff_builder',
  label: new TranslatableMarkup('Core Field Diff'),
  field_types: [
    'decimal',
    'integer',
    'float',
    'email',
    'telephone',
    'date',
    'daterange',
    'uri',
    'string',
    'timestamp',
    'created',
    'string_long',
    'language',
    'uuid',
    'map',
    'datetime',
    'boolean',
  ],
)]
class CoreFieldBuilder extends FieldDiffBuilderBase {

  /**
   * Constructs a CoreFieldBuilder object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    DiffEntityParser $entity_parser,
    protected RendererInterface $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_parser);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('diff.entity_parser'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items): array {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        if (isset($values['value'])) {
          $value = $field_item->view(['label' => 'hidden']);
          $result[$field_key][] = $this->renderer->renderInIsolation($value);
        }
      }
    }

    return $result;
  }

}

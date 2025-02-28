<?php

namespace Drupal\rest_views_revisions\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter as ERFEntityFormatter;
use Drupal\rest_views\Plugin\Field\FieldFormatter\EntityReferenceExportFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bridge plugin integrating entity_reference_revisions with rest_views.
 *
 * @FieldFormatter(
 *   id = "entity_reference_revisions_export",
 *   label = @Translation("Export rendered entity"),
 *   description = @Translation("Export the entity rendered by entity_view()."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class EntityReferenceRevisionsExportFormatter extends EntityReferenceExportFormatter {

  /**
   * Instance of the adapted class.
   *
   * @var \Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter
   */
  protected $entityFormatter;

  /**
   * EntityReferenceRevisionsExportFormatter constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter $entityFormatter
   *   The entity_reference_revisions formatter.
   */
  public function __construct($plugin_id,
                              $plugin_definition,
                              FieldDefinitionInterface $field_definition,
                              array $settings,
                              $label,
                              $view_mode,
                              array $third_party_settings,
                              LoggerChannelFactoryInterface $logger_factory,
                              EntityTypeManagerInterface $entity_type_manager,
                              EntityDisplayRepositoryInterface $entity_display_repository,
                              ERFEntityFormatter $entityFormatter
  ) {
    parent::__construct($plugin_id,
                        $plugin_definition,
                        $field_definition,
                        $settings,
                        $label,
                        $view_mode,
                        $third_party_settings,
                        $logger_factory,
                        $entity_type_manager,
                        $entity_display_repository);
    $this->entityFormatter = $entityFormatter;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   */
  public static function create(ContainerInterface $container,
                                array $configuration,
                                $plugin_id,
                                $plugin_definition) {

    // Instantiate the entity_reference_revisions formatter.
    $entityFormatter = ERFEntityFormatter::create($container,
                                                  $configuration,
                                                  $plugin_id,
                                                  $plugin_definition);

    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $entityFormatter
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items): void {
    // Delegate this method to entity_reference_revisions.
    $this->entityFormatter->prepareView($entities_items);
  }

}

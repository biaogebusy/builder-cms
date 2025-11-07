<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\DiffGenerator;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Drupal\entity_share_diff\Service\EntityParserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Diff plugins.
 */
abstract class DiffGeneratorPluginBase extends PluginBase implements DiffGeneratorInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The remote manager service.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity parser service.
   *
   * @var \Drupal\entity_share_diff\Service\EntityParserInterface
   */
  protected $entityParser;

  /**
   * The 'Remote' entity.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface
   */
  protected $remote;

  /**
   * Constructs a DiffManagerBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remoteManager
   *   The remote manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\entity_share_diff\Service\EntityParserInterface $entity_parser
   *   The entity parser service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    RemoteManagerInterface $remoteManager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityParserInterface $entity_parser
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->remoteManager = $remoteManager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityParser = $entity_parser;
    $this->remote = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_share_client.remote_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_share_diff.entity_parser')
    );
  }

  /**
   * Returns Remote entity.
   */
  public function getRemote() {
    return $this->remote;
  }

  /**
   * Sets Remote entity.
   */
  public function setRemote($remote) {
    $this->remote = $remote;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldStorageDefinitionInterface $field_definition) {
    return TRUE;
  }

}

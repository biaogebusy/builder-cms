<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Import status entities.
 */
class EntityImportStatusListBuilder extends EntityListBuilder {

  /**
   * The format for the import time.
   *
   * Long format, with seconds.
   */
  const IMPORT_DATE_FORMAT = 'F j, Y - H:i:s';

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The import policies manager.
   *
   * @var \Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager
   */
  protected $policiesManager;

  /**
   * Constructs a new UserListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager $policies_manager
   *   The policies manager.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    DateFormatterInterface $date_formatter,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    LanguageManagerInterface $language_manager,
    ImportPolicyPluginManager $policies_manager
  ) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->languageManager = $language_manager;
    $this->policiesManager = $policies_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('language_manager'),
      $container->get('plugin.manager.entity_share_client_policy')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    $header['id'] = $this->t('ID');
    $header['entity_uuid'] = $this->t('Entity UUID');
    $header['entity_id'] = $this->t('Entity ID');
    $header['langcode'] = $this->t('Language');
    $header['entity_label'] = $this->t('Link to entity');
    $header['entity_type_id'] = $this->t('Entity type');
    $header['entity_bundle'] = $this->t('Bundle');
    $header['remote_website'] = $this->t('Remote');
    $header['channel_id'] = $this->t('Channel');
    $header['last_import'] = $this->t('Last import');
    $header['policy'] = $this->t('Policy');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $import_status_language = $entity->language();
    $imported_entity_type_id = $entity->entity_type_id->value;
    $imported_entity_bundle = $entity->entity_bundle->value;
    $imported_entity_id = $entity->entity_id->value;
    $imported_entity_uuid = $entity->entity_uuid->value;
    $remote_id = $entity->remote_website->value;
    $channel_id = $entity->channel_id->value;

    $row = [];
    $row['id'] = $entity->id();

    // Basic keys of imported entity.
    $row['entity_uuid'] = $imported_entity_uuid;
    $row['entity_id'] = $imported_entity_id;
    $row['langcode'] = $import_status_language->getName();

    // Load the imported entity.
    $imported_entity_storage = $this->entityTypeManager
      ->getStorage($imported_entity_type_id);
    $imported_entity = $imported_entity_storage->load($imported_entity_id);
    if ($imported_entity != NULL) {
      // Label and link to entity should respect the language.
      if (!$import_status_language->isLocked()) {
        $imported_entity = $imported_entity->getTranslation($import_status_language->getId());
      }
      try {
        $row['entity_label'] = $imported_entity->toLink($imported_entity->label());
      }
      catch (UndefinedLinkTemplateException $exception) {
        $row['entity_label'] = $imported_entity->label();
      }
    }
    else {
      $row['entity_label'] = $this->t('Missing entity');
    }

    // Label of entity type.
    $row['entity_type_id'] = $imported_entity_storage->getEntityType()->getLabel();

    // Imported entity's bundle.
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($imported_entity_type_id);
    $row['entity_bundle'] = $bundle_info[$imported_entity_bundle]['label'] ?? $imported_entity_bundle;

    // Remote website.
    $remote = $this->entityTypeManager
      ->getStorage('remote')
      ->load($remote_id);
    if ($remote != NULL) {
      $row['remote_website'] = $remote->label();
    }
    else {
      $row['remote_website'] = $remote_id;
    }

    // Machine name of the import channel.
    $row['channel_id'] = $channel_id;

    // Last import time.
    $row['last_import'] = $this->dateFormatter->format($entity->getLastImport(), 'custom', self::IMPORT_DATE_FORMAT);

    // Label of the import policy (or raw value if not found).
    $policy = $entity->getPolicy();
    $available_policies = $this->policiesManager->getDefinitions();
    if (isset($available_policies[$policy])) {
      $policy = $available_policies[$policy]['label'];
    }
    $row['policy'] = $policy;

    return $row + parent::buildRow($entity);
  }

}

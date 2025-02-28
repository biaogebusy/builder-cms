<?php

namespace Drupal\entity_print;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Provides dynamic permissions for entity_print.
 */
class EntityPrintPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new EntityPrintPermissions.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translation_manager
   *   The translation manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, TranslationManager $translation_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->stringTranslation = $translation_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('string_translation')
    );
  }

  /**
   * Returns an array of entity_print permissions.
   */
  public function getPermissions() {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $content_entity_types */
    // Get all EntityTypes for the group "content".
    $content_entity_types = array_filter($this->entityTypeManager->getDefinitions(), function ($entity_type) {
      return $entity_type->getGroup() === 'content';
    });

    $permissions = [];
    foreach ($content_entity_types as $content_entity_type) {
      $permissions['entity print access type ' . $content_entity_type->id()] = [
        'title' => $this->t('%entity_label: Use all print engines', [
          '%entity_label' => $content_entity_type->getLabel(),
        ]),
      ];

      // Add 1 permission for each bundle.
      $entity_type_bundles = $this->entityTypeBundleInfo->getBundleInfo($content_entity_type->id());

      // Don't bother creating a new permission if there is only 1 bundle.
      if (count($entity_type_bundles) === 1) {
        continue;
      }

      foreach ($entity_type_bundles as $bundle_key => $entity_type_bundle) {
        $permissions['entity print access bundle ' . $bundle_key] = [
          'title' => $this->t('%entity_bundle_label: Use all print engines', [
            '%entity_bundle_label' => $entity_type_bundle['label'] ?? $bundle_key,
          ]),
        ];
      }
    }

    return $permissions;
  }

}

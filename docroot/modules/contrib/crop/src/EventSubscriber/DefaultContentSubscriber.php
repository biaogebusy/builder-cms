<?php

declare(strict_types=1);

namespace Drupal\crop\EventSubscriber;

use Drupal\Core\DefaultContent\ExportMetadata;
use Drupal\Core\DefaultContent\PreEntityImportEvent;
use Drupal\Core\DefaultContent\PreExportEvent;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem;
use Drupal\crop\Entity\Crop;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles importing and exporting crop entities as default content.
 *
 * @internal
 *   This is an internal part of Crop API and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 */
final class DefaultContentSubscriber implements EventSubscriberInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreExportEvent::class => 'preExport',
      PreEntityImportEvent::class => 'preEntityImport',
    ];
  }

  /**
   * Reacts before a crop entity is exported.
   *
   * @param \Drupal\Core\DefaultContent\PreExportEvent $event
   *   The event being handled.
   */
  public function preExport(PreExportEvent $event): void {
    // This is coupled to the concrete Crop class because we need to work with
    // base fields that it defines.
    if ($event->entity instanceof Crop) {
      $event->setCallback('entity_id', $this->toUuid(...));
    }
  }

  /**
   * Converts a crop entity's target entity ID to a UUID.
   *
   * @param \Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem $item
   *   The field item being exported (i.e., the `entity_id` field).
   * @param \Drupal\Core\DefaultContent\ExportMetadata $metadata
   *   Exporter metadata for the entity being exported.
   *
   * @return array
   *   The exported representation of the field item.
   */
  private function toUuid(IntegerItem $item, ExportMetadata $metadata): array {
    $crop = $item->getEntity();
    assert($crop instanceof Crop);

    $entity = $this->entityTypeManager->getStorage($crop->entity_type->value)
      ->load($item->value);
    if ($entity instanceof ContentEntityInterface) {
      $metadata->addDependency($entity);
      return [
        'uuid' => $entity->uuid(),
      ];
    }
    $this->logger?->warning('The entity associated with crop @uuid (@id) cannot be loaded. This will probably cause unexpected behavior when the crop is imported later.', [
      '@uuid' => $crop->uuid(),
      '@id' => $crop->id(),
    ]);
    return $item->getValue();
  }

  /**
   * Reacts before a crop entity is imported.
   *
   * @param \Drupal\Core\DefaultContent\PreEntityImportEvent $event
   *   The event being handled.
   */
  public function preEntityImport(PreEntityImportEvent $event): void {
    if ($event->metadata['entity_type'] !== 'crop') {
      return;
    }
    foreach ($event->data as &$translation) {
      $target_type = $translation['entity_type'][0]['value'];
      $target_uuid = $translation['entity_id'][0]['uuid'] ?? NULL;
      // If we weren't able to convert to a UUID on export, there's nothing
      // we can do here.
      if ($target_uuid === NULL) {
        continue;
      }
      $target = $this->entityRepository->loadEntityByUuid($target_type, $target_uuid);
      if ($target === NULL) {
        $this->logger?->warning('The entity associated with crop @uuid (@target_type @target_uuid) could not be loaded.', [
          '@uuid' => $event->metadata['uuid'],
          '@target_type' => $target_type,
          '@target_uuid' => $target_uuid,
        ]);
      }
      $translation['entity_id'][0] = [
        // If we couldn't load the entity, at least provide an integer value.
        'value' => $target?->id() ?? 0,
      ];
    }
  }

}

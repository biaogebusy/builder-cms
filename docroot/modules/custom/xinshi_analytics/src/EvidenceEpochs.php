<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\user\UserInterface;
use Drupal\xinshi_analytics\Entity\AnalyticsDataset;

/** Durable change markers must not disappear with cache-tag garbage collection. */
final class EvidenceEpochs {

  /** Stores only opaque markers, never results, field values or credentials. */
  public function __construct(private readonly KeyValueFactoryInterface $keyValues) {}

  /** Projects must call this when external access rules change outside Drupal save hooks. */
  public function invalidate(): void {
    $this->change('policy');
  }

  /** Configuration includes roles, scopes, mappings, fields, languages and module changes. */
  public function configurationChanged(): void {
    $this->invalidate();
  }

  /** Entity saves and deletes conservatively invalidate the whole affected bundle. */
  public function entityChanged(EntityInterface $entity): void {
    if ($entity instanceof EntityPublishedInterface) {
      $this->change('source:' . $entity->getEntityTypeId() . ':' . $entity->bundle());
    }
    if ($entity instanceof UserInterface) {
      $this->change('user:' . $entity->id());
    }
  }

  /** Reads fresh persistent markers, including changes made by another request. */
  public function current(AnalyticsDataset $dataset, string $uid): array {
    $names = ['policy', 'user:' . $uid];
    foreach ($dataset->get('bundles') as $bundle) {
      $names[] = 'source:' . $dataset->get('entity_type') . ':' . $bundle;
    }
    sort($names, SORT_STRING);
    $stored = $this->keyValues->get('xinshi_analytics.evidence_epochs')->getMultiple($names);
    return array_replace(array_fill_keys($names, '0'), $stored);
  }

  /** Capture identity/configuration markers before loading accounts or dataset definitions. */
  public function identity(string $uid): array {
    $names = ['policy', 'user:' . $uid];
    $stored = $this->keyValues->get('xinshi_analytics.evidence_epochs')->getMultiple($names);
    return array_replace(array_fill_keys($names, '0'), $stored);
  }

  /** Random markers avoid lost increments during concurrent updates and are never pruned. */
  private function change(string $name): void {
    $this->keyValues->get('xinshi_analytics.evidence_epochs')->set($name, bin2hex(random_bytes(32)));
  }

}

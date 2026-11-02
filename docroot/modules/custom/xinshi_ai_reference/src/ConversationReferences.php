<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Coordinates relationship saves and deletion with conversation-before-message locks. */
final class ConversationReferences {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly ResourceTypeRepositoryInterface $resources,
  ) {}

  /** Lock message identities in a stable order inside the caller's transaction. */
  public function lockTargets(array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    sort($ids, SORT_NUMERIC);
    $rows = [];
    foreach (array_chunk($ids, 100) as $batch) {
      $rows += $this->database->select('node', 'n')->fields('n', ['nid', 'vid', 'type'])
        ->condition('nid', $batch, 'IN')->orderBy('nid')->forUpdate()->execute()->fetchAllAssoc('nid');
    }
    return $rows;
  }

  /** Prevent direct entity saves from racing deletion or reintroducing missing references. */
  public function guardSave(NodeInterface $node): void {
    $field = $this->resources->get('node', 'conversation')?->getInternalName('sessions') ?? 'sessions';
    if (!$node->hasField($field)) {
      return;
    }
    // Core has already started the entity save transaction before invoking presave hooks.
    if (!$node->isNew() && !$this->database->select('node', 'n')->fields('n', ['nid'])
      ->condition('nid', $node->id())->forUpdate()->execute()->fetchField()) {
      throw new ConversationReferenceConflict();
    }
    $ids = [];
    foreach ($node->getTranslationLanguages() as $language) {
      foreach ($node->getTranslation($language->getId())->get($field) as $item) {
        if ($item->target_id) {
          $ids[(int) $item->target_id] = (int) $item->target_id;
        }
      }
    }
    $rows = $this->lockTargets($ids);
    $missing = array_diff_key($ids, $rows);
    if ($missing) {
      $known = $node->isNew() ? [] : $this->storedTargets(
        $node->getFieldDefinition($field), $missing, (int) $node->id(), FALSE);
      // Individual message deletion can leave old links; a new missing link is never valid.
      if (array_diff($missing, $known)) {
        throw new ConversationReferenceConflict();
      }
    }
    $storage = $this->entities->getStorage('node');
    $storage->resetCache(array_keys($rows));
    $sessions = $storage->loadMultiple(array_keys($rows));
    foreach ($rows as $id => $row) {
      // A locking read is current even if an earlier repeatable-read snapshot is stale.
      if (!isset($sessions[$id]) ||
        (string) $row->vid !== (string) $sessions[$id]->getRevisionId()) {
        throw new ConversationReferenceConflict();
      }
    }
    foreach ($node->getTranslationLanguages() as $language) {
      foreach ($node->getTranslation($language->getId())->get($field) as $item) {
        if ($item->target_id) {
          // JSON:API normalization may have cached target entities before these locks.
          $item->setValue(['target_id' => $item->target_id]);
        }
      }
    }
  }

  /** Preserve references from other conversations, including all translations and revisions. */
  public function sharedTargets(FieldDefinitionInterface $field, array $ids, int $conversation): array {
    return $this->storedTargets($field, $ids, $conversation, TRUE);
  }

  private function storedTargets(FieldDefinitionInterface $field, array $ids, int $conversation, bool $other): array {
    $definition = $field->getFieldStorageDefinition();
    $mapping = $this->entities->getStorage('node')->getTableMapping();
    $tables = [$mapping->getDedicatedDataTableName($definition)];
    if ($other) {
      $tables[] = $mapping->getDedicatedRevisionTableName($definition);
    }
    $column = $mapping->getFieldColumnName($definition, 'target_id');
    $found = [];
    foreach ($tables as $table) {
      foreach (array_chunk($ids, 100) as $batch) {
        // Locking reads see committed references after waiting for the target message locks.
        $values = $this->database->select($table, 'r')->fields('r', [$column])
          ->condition($column, $batch, 'IN')->condition('entity_id', $conversation, $other ? '<>' : '=')
          ->condition('deleted', 0)->range(0, 10001)->forUpdate()->execute()->fetchCol();
        if (count($values) > 10000) {
          throw new ServiceUnavailableHttpException(NULL, 'Conversation reference scan limit exceeded.');
        }
        foreach ($values as $id) {
          $found[(int) $id] = (int) $id;
        }
      }
    }
    return $found;
  }

}

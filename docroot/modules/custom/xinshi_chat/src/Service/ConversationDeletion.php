<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Service;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai_reference\ConversationReferenceConflict;
use Drupal\xinshi_ai_reference\ConversationReferences;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Deletes one owned conversation and its unshared current messages atomically. */
final class ConversationDeletion {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly ResourceTypeRepositoryInterface $resources,
    private readonly ConversationReferences $references,
  ) {}

  /** Repeated deletes succeed after a lost acknowledgement; unrelated task stores are untouched. */
  public function delete(string $uuid): void {
    if (!Uuid::isValid($uuid)) {
      throw new BadRequestHttpException('Invalid conversation UUID.');
    }
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $storage = $this->entities->getStorage('node');
    $transaction = $this->database->startTransaction();
    try {
      $nid = $this->database->select('node', 'n')->fields('n', ['nid'])
        ->condition('uuid', $uuid)->condition('type', 'conversation')
        ->forUpdate()->execute()->fetchField();
      if (!$nid) {
        unset($transaction);
        return;
      }
      $storage->resetCache([$nid]);
      $chat = $storage->load($nid);
      $this->requireDelete($chat, 'conversation');
      $resource = $this->resources->get('node', 'conversation');
      $field = $resource?->getInternalName('sessions');
      if (!$field || $resource->isInternal() || !$resource->isFieldEnabled($field) ||
        !$chat->hasField($field)) {
        throw new AccessDeniedHttpException();
      }
      $ids = [];
      foreach ($chat->getTranslationLanguages() as $language) {
        $items = $chat->getTranslation($language->getId())->get($field);
        foreach ($items as $item) {
          if ($item->target_id) {
            $ids[(int) $item->target_id] = (int) $item->target_id;
          }
        }
      }
      if (count($ids) > 10000) {
        throw new ServiceUnavailableHttpException(NULL, 'Conversation deletion limit exceeded.');
      }
      $rows = $this->references->lockTargets($ids);
      $storage->resetCache(array_keys($rows));
      $sessions = $storage->loadMultiple(array_keys($rows));
      foreach ($rows as $id => $row) {
        if ($row->type !== 'ai_session' || !isset($sessions[$id]) ||
          (string) $sessions[$id]->getRevisionId() !== (string) $row->vid) {
          throw new ConversationReferenceConflict();
        }
      }
      foreach ($chat->getTranslationLanguages() as $language) {
        // Field access can load protected targets and write the entity cache. Lock targets first.
        $items = $chat->getTranslation($language->getId())->get($field);
        if (!$items->access('view', $this->account)) {
          throw new AccessDeniedHttpException();
        }
      }
      $shared = $this->references->sharedTargets($chat->getFieldDefinition($field), array_keys($rows), (int) $nid);
      $sessions = array_diff_key($sessions, $shared);
      foreach ($sessions as $session) {
        $this->requireDelete($session, 'ai_session');
      }
      // All access and reference checks finish before the first entity deletion.
      $storage->delete([$nid => $chat] + $sessions);
      unset($transaction);
    }
    catch (\Throwable $error) {
      if (isset($transaction)) {
        $transaction->rollBack();
        unset($transaction);
      }
      $storage->resetCache();
      throw $error;
    }
  }

  private function requireDelete(?NodeInterface $node, string $bundle): void {
    if (!$node || $node->bundle() !== $bundle ||
      (string) $node->getOwnerId() !== (string) $this->account->id() ||
      !$node->access('delete', $this->account)) {
      throw new AccessDeniedHttpException();
    }
    foreach ($node->getTranslationLanguages() as $language) {
      if (!$node->getTranslation($language->getId())->access('delete', $this->account)) {
        throw new AccessDeniedHttpException();
      }
    }
  }

}

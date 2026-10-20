<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Validates locators and storage copies; Node alone authorizes the referenced result. */
final class TaskReferenceGuard {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly ResourceTypeRepositoryInterface $resources,
    private readonly AccountInterface $account,
    private readonly ChatSessionSnapshot $snapshots,
    private readonly SessionFieldsWriter $writer,
    private readonly Connection $database,
    private readonly ChatStorage $protocol,
  ) {}

  /** Serialize direct saves too, so an unchanged stale entity cannot overwrite a concurrent CAS. */
  public function presave(NodeInterface $node): void {
    if ($node->bundle() === 'ai_session' && !$node->isNew()) {
      // SqlContentEntityStorage already holds the save transaction at this hook boundary.
      $row = $this->database->select('node', 'n')->fields('n', ['vid'])
        ->condition('nid', $node->id())->forUpdate()->execute()->fetchAssoc();
      $original = $this->entities->getStorage('node')->loadUnchanged($node->id());
      // A locking read sees the current revision even if core opened a repeatable-read snapshot earlier.
      if (($this->snapshots->usesNewFields($node) ||
        ($original && $this->snapshots->usesNewFields($original))) &&
        (!$row || (string) $row['vid'] !== (string) $node->getLoadedRevisionId() ||
          !$original || (string) $row['vid'] !== (string) $original->getRevisionId())) {
        $this->reject();
      }
    }
    $this->validate($node);
  }

  /** Entity access covers JSON:API single, collection, relationship and included resources. */
  public function access(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    $current = $node->bundle() === 'ai_session' && !$node->isNew() && !$node->isDefaultRevision() ?
      $this->entities->getStorage('node')->loadUnchanged($node->id()) : NULL;
    if (!$this->marked($node) && !($current instanceof NodeInterface && $this->marked($current))) {
      return AccessResult::neutral();
    }
    return AccessResult::forbiddenIf($account->isAnonymous() ||
      (string) $account->id() !== (string) $node->getOwnerId() ||
      ($current && (string) $account->id() !== (string) $current->getOwnerId()))
      ->cachePerUser()->setCacheMaxAge(0)->addCacheableDependency($node);
  }

  /** JSON:API linkage can expose IDs without entity access; guard the relationship field too. */
  public function relationshipAccess(FieldItemListInterface $items, AccountInterface $account): AccessResultInterface {
    $node = $items->getEntity();
    if (!$node instanceof NodeInterface || $node->bundle() !== 'conversation' ||
      $items->getName() !== $this->field($node, 'sessions')) {
      return AccessResult::neutral();
    }
    if ($account->isAnonymous() || (string) $account->id() !== (string) $node->getOwnerId()) {
      foreach ($items->referencedEntities() as $session) {
        if ($session instanceof NodeInterface && $this->marked($session)) {
          return AccessResult::forbidden()->cachePerUser()->setCacheMaxAge(0)->addCacheableDependency($node);
        }
      }
    }
    return AccessResult::neutral()->cachePerUser()->setCacheMaxAge(0);
  }

  /** Guard entity saves, including direct JSON:API writes and ConversationWriter appends. */
  public function validate(NodeInterface $node): void {
    if ($node->bundle() === 'conversation') {
      $field = $this->field($node, 'sessions');
      if (!$node->hasField($field)) {
        return;
      }
      foreach ($node->getTranslationLanguages() as $language) {
        foreach ($node->getTranslation($language->getId())->get($field)->referencedEntities() as $session) {
          if ($session instanceof NodeInterface && $session->bundle() === 'ai_session') {
            $this->requireSelectedVersion($session);
          }
          if (!$session instanceof NodeInterface || !$this->marked($session)) {
            continue;
          }
          foreach ($session->getTranslationLanguages() as $session_language) {
            $ref = $this->reference($session->getTranslation($session_language->getId()));
            if ($ref['chatId'] !== $node->uuid() ||
              (string) $session->getOwnerId() !== (string) $node->getOwnerId()) {
              $this->reject();
            }
          }
        }
      }
      return;
    }
    if ($node->bundle() !== 'ai_session') {
      return;
    }
    $this->requireSelectedVersion($node);
    // The default stored entity is authoritative even when the caller edits an older revision.
    $original = $node->isNew() ? NULL :
      $this->entities->getStorage('node')->loadUnchanged($node->id());
    $loaded = !$node->isNew() && $node->getLoadedRevisionId() ?
      $this->entities->getStorage('node')->loadRevisionUnchanged($node->getLoadedRevisionId()) : NULL;
    try {
      $this->validateSnapshots($node, $original);
      if ($loaded instanceof NodeInterface) {
        $this->validateSnapshots($node, $loaded);
      }
    }
    catch (ChatSessionFormatException) {
      $this->reject();
    }
    $protected_originals = array_filter([$original, $loaded],
      fn($entity) => $entity instanceof NodeInterface && $this->marked($entity));
    if (!$this->marked($node) && $protected_originals === []) {
      return;
    }
    if ($this->account->isAnonymous() ||
      (string) $node->getOwnerId() !== (string) $this->account->id() ||
      ($original && (string) $original->getOwnerId() !== (string) $node->getOwnerId())) {
      $this->reject();
    }
    $expected = NULL;
    foreach ($node->getTranslationLanguages() as $language) {
      $translation = $node->getTranslation($language->getId());
      $ref = $this->reference($translation);
      if ($expected !== NULL && $expected !== $ref) {
        $this->reject();
      }
      $expected = $ref;
      foreach ($protected_originals as $protected_original) {
        if ($this->reference($protected_original) !== $ref ||
          (string) $protected_original->getOwnerId() !== (string) $node->getOwnerId()) {
          $this->reject();
        }
      }
      $content = $this->field($translation, 'content');
      $role = $this->field($translation, 'session_role');
      $v2 = $this->snapshots->usesNewFields($translation);
      $allowed = $v2 ? array_map(fn($name) => $this->field($translation, $name),
        [...ChatSessionSnapshot::NEW_FIELDS, ...ChatSessionSnapshot::PLAN_FIELDS,
          'content', 'reasoning_content', 'summary',
          'img', 'style_preset']) : [$content];
      $allowed[] = $role;
      if ($translation->label() !== 'Protected task result' || !$translation->hasField($role) ||
        $translation->get($role)->value !== 'assistant') {
        $this->reject();
      }
      // Unknown custom fields are closed by default; new presentation copies require review.
      foreach ($translation->getFieldDefinitions() as $name => $definition) {
        if ($definition->isComputed() || $definition->getFieldStorageDefinition()->isBaseField() ||
          in_array($name, $allowed, TRUE)) {
          continue;
        }
        $items = $translation->get($name);
        if ($items->isEmpty()) {
          continue;
        }
        if (!in_array($name, [$this->field($translation, 'total_tokens'),
          $this->field($translation, 'uiparsing')], TRUE) ||
          count($items) !== 1 || !in_array($items->value, [0, '0', FALSE], TRUE)) {
          $this->reject();
        }
      }
    }
    $found = $this->entities->getStorage('node')->loadByProperties([
      'type' => 'conversation', 'uuid' => $expected['chatId'],
    ]);
    $conversation = reset($found);
    if (!$conversation || (string) $conversation->getOwnerId() !== (string) $node->getOwnerId() ||
      !$conversation->access('update', $this->account)) {
      $this->reject();
    }
  }

  /** Require the full contract and the writer's exact locked transition for every changed snapshot. */
  private function validateSnapshots(NodeInterface $node, ?NodeInterface $original): void {
    $current_v2 = $original && $this->snapshots->usesNewFields($original);
    foreach ($node->getTranslationLanguages() as $language) {
      $translation = $node->getTranslation($language->getId());
      $v2 = $this->snapshots->usesNewFields($translation);
      if (!$v2 && !$current_v2) {
        continue;
      }
      if (!$v2 || ($original && !$current_v2) ||
        ($original && (string) $original->getOwnerId() !== (string) $node->getOwnerId())) {
        $this->reject();
      }
      $snapshot = $this->snapshots->validate($translation);
      if ($node->isNew() && isset($snapshot->plan_context->legacyPlanRunId)) {
        $this->reject();
      }
      if ($original && (!$original->hasTranslation($language->getId()) ||
        (ChatSessionFormat::encode($snapshot) !== ChatSessionFormat::encode(
          $this->snapshots->validate($original->getTranslation($language->getId()))) &&
          !$this->writer->permits($node, $original)))) {
        $this->reject();
      }
    }
    if ($current_v2 && array_keys($node->getTranslationLanguages()) !==
      array_keys($original->getTranslationLanguages())) {
      $this->reject();
    }
  }

  /** Historical revisions remain readable; only current-format entities may be saved or linked. */
  private function requireSelectedVersion(NodeInterface $node): void {
    $version = $this->protocol->version();
    foreach ($node->getTranslationLanguages() as $language) {
      $translation = $node->getTranslation($language->getId());
      if (!$this->snapshots->usesNewFields($translation)) {
        if ($version !== 1) {
          $this->reject();
        }
        continue;
      }
      try {
        if ($this->snapshots->validate($translation)->storage_version !== $version) {
          $this->reject();
        }
      }
      catch (ChatSessionFormatException) {
        $this->reject();
      }
    }
  }

  /** Recognize corrupt markers too, so an invalid locator cannot fall back to ordinary text. */
  private function marked(NodeInterface $node): bool {
    if ($node->bundle() !== 'ai_session') {
      return FALSE;
    }
    foreach ($node->getTranslationLanguages() as $language) {
      $translation = $node->getTranslation($language->getId());
      foreach (['storage_mode', 'result_reference'] as $public) {
        $field = $this->field($translation, $public);
        if ($translation->hasField($field) &&
          (($public === 'storage_mode' && $translation->get($field)->value === 'protected_reference') ||
          ($public === 'result_reference' && !$translation->get($field)->isEmpty()))) {
          return TRUE;
        }
      }
      $name = $this->field($translation, 'content');
      $content = $translation->hasField($name) ? (string) $translation->get($name)->value : '';
      if (ChatSessionSnapshot::managedEnvelope($content, ['xinshi-protected-run'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /** Require a bounded, exact shape bound to this session UUID. */
  private function reference(NodeInterface $node): array {
    if ($this->snapshots->usesNewFields($node)) {
      try {
        $value = $this->snapshots->validate($node);
        if ($value->storage_mode !== 'protected_reference') {
          $this->reject();
        }
        return (array) $value->result_reference;
      }
      catch (ChatSessionFormatException) {
        $this->reject();
      }
    }
    $name = $this->field($node, 'content');
    $content = $node->hasField($name) ? (string) $node->get($name)->value : '';
    try {
      $decoded = strlen($content) <= 300 ? ChatSessionFormat::decode($content) : NULL;
      $ref = $decoded instanceof \stdClass ? (array) $decoded : NULL;
    }
    catch (ChatSessionFormatException) {
      $this->reject();
    }
    if (!is_array($ref)) {
      $this->reject();
    }
    $keys = array_keys($ref);
    sort($keys);
    if ($keys !== ['chatId', 'kind', 'runId', 'version'] ||
      $ref['kind'] !== 'xinshi-protected-run' || $ref['version'] !== 1 ||
      !is_string($ref['runId']) || !is_string($ref['chatId']) ||
      !Uuid::isValid($ref['runId']) || !Uuid::isValid($ref['chatId']) ||
      $ref['runId'] !== $node->uuid()) {
      $this->reject();
    }
    return ['kind' => $ref['kind'], 'version' => 1, 'runId' => $ref['runId'], 'chatId' => $ref['chatId']];
  }

  private function field(NodeInterface $node, string $public): string {
    return $this->resources->get('node', $node->bundle())?->getInternalName($public) ?? $public;
  }

  private function reject(): never {
    throw new UnprocessableEntityHttpException('Invalid protected task reference.');
  }

}

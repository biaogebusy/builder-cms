<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
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
  ) {}

  /** Entity access covers JSON:API single, collection, relationship and included resources. */
  public function access(NodeInterface $node, AccountInterface $account): AccessResultInterface {
    if (!$this->marked($node)) {
      return AccessResult::neutral();
    }
    return AccessResult::forbiddenIf($account->isAnonymous() ||
      (string) $account->id() !== (string) $node->getOwnerId())
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
    $original = $node->getOriginal() ?? ($node->isNew() ? NULL :
      $this->entities->getStorage('node')->loadUnchanged($node->id()));
    if (!$this->marked($node) && !($original instanceof NodeInterface && $this->marked($original))) {
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
      if ($original instanceof NodeInterface && $this->marked($original) &&
        $this->reference($original) !== $ref) {
        $this->reject();
      }
      $content = $this->field($translation, 'content');
      $role = $this->field($translation, 'session_role');
      if ($translation->label() !== 'Protected task result' || !$translation->hasField($role) ||
        $translation->get($role)->value !== 'assistant') {
        $this->reject();
      }
      // Unknown custom fields are closed by default; new presentation copies require review.
      foreach ($translation->getFieldDefinitions() as $name => $definition) {
        if ($definition->isComputed() || $definition->getFieldStorageDefinition()->isBaseField() ||
          in_array($name, [$content, $role], TRUE)) {
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

  /** Recognize corrupt markers too, so an invalid locator cannot fall back to ordinary text. */
  private function marked(NodeInterface $node): bool {
    if ($node->bundle() !== 'ai_session') {
      return FALSE;
    }
    foreach ($node->getTranslationLanguages() as $language) {
      $translation = $node->getTranslation($language->getId());
      $name = $this->field($translation, 'content');
      $content = $translation->hasField($name) ? (string) $translation->get($name)->value : '';
      $value = json_decode($content, TRUE);
      $malformed = json_last_error() !== JSON_ERROR_NONE &&
        str_starts_with(ltrim($content), '{') && str_contains($content, '"xinshi-protected-run"');
      if ($malformed || (is_array($value) && ($value['kind'] ?? NULL) === 'xinshi-protected-run')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /** Require a bounded, exact shape bound to this session UUID. */
  private function reference(NodeInterface $node): array {
    $name = $this->field($node, 'content');
    $content = $node->hasField($name) ? (string) $node->get($name)->value : '';
    $ref = strlen($content) <= 300 ? json_decode($content, TRUE) : NULL;
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

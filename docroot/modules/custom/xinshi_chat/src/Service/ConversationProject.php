<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Service;

use Drupal\Component\Uuid\Uuid;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai_reference\ConversationReferenceConflict;
use Drupal\xinshi_ai_reference\ConversationReferences;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Appends a conversation under project, then conversation locks. */
final class ConversationProject {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly ResourceTypeRepositoryInterface $resources,
    private readonly ConversationReferences $references,
    private readonly TimeInterface $time,
  ) {}

  /** Confirm association before task handoff; repeated requests do not create revisions. */
  public function link(string $uuid, string $project_uuid): void {
    if (!Uuid::isValid($uuid) || !Uuid::isValid($project_uuid)) {
      throw new BadRequestHttpException('Invalid conversation or project UUID.');
    }
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $storage = $this->entities->getStorage('node');
    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select('node', 'n')->fields('n', ['nid', 'vid'])
        ->condition('uuid', $project_uuid)->condition('type', 'project')
        ->forUpdate()->execute()->fetchAssoc();
      if (!$row) {
        throw new NotFoundHttpException();
      }
      $storage->resetCache([$row['nid']]);
      $project = $storage->load($row['nid']);
      if (!$project || (string) $project->getRevisionId() !== (string) $row['vid'] ||
        !$project->isDefaultRevision() || !$project->isLatestRevision()) {
        throw new ConversationReferenceConflict();
      }
      $resource = $this->resources->get('node', 'project');
      $field = $resource?->getInternalName('field_conversations');
      if (!$field || $resource->isInternal() || !$resource->isFieldEnabled($field) ||
        !$project->hasField($field) || !$project->access('update', $this->account) ||
        !$project->access('view', $this->account)) {
        throw new AccessDeniedHttpException();
      }
      $chat_id = $this->database->select('node', 'n')->fields('n', ['nid'])
        ->condition('uuid', $uuid)->condition('type', 'conversation')->execute()->fetchField();
      if (!$chat_id) {
        throw new NotFoundHttpException();
      }
      // The guard locks every referenced conversation before field access can load its entities.
      $already_linked = in_array((string) $chat_id,
        array_map('strval', array_column($project->get($field)->getValue(), 'target_id')), TRUE);
      $count = 0;
      foreach ($project->getTranslationLanguages() as $language) {
        $count += count($project->getTranslation($language->getId())->get($field));
      }
      if ($count + (int) !$already_linked > 10000) {
        throw new ServiceUnavailableHttpException(NULL, 'Project relationship limit exceeded.');
      }
      if (!$already_linked) {
        $project->get($field)->appendItem(['target_id' => $chat_id]);
      }
      $this->references->guardSave($project, $field);
      $chat = $storage->load($chat_id);
      $chat_resource = $this->resources->get('node', 'conversation');
      if (!$chat || !$chat_resource || $chat_resource->isInternal() ||
        (string) $chat->getOwnerId() !== (string) $this->account->id() ||
        !$chat->access('view', $this->account)) {
        throw new AccessDeniedHttpException();
      }
      foreach ($chat->getTranslationLanguages() as $language) {
        if (!$chat->getTranslation($language->getId())->access('view', $this->account)) {
          throw new AccessDeniedHttpException();
        }
      }
      foreach ($project->getTranslationLanguages() as $language) {
        $translation = $project->getTranslation($language->getId());
        if (!$translation->access('view', $this->account) ||
          !$translation->access('update', $this->account) ||
          !$translation->get($field)->access('view', $this->account) ||
          !$translation->get($field)->access('edit', $this->account)) {
          throw new AccessDeniedHttpException();
        }
      }
      if (!$already_linked) {
        // Like ConversationWriter, discard dangling references before validating the append.
        $project->set($field, array_map(static fn(NodeInterface $node): array =>
          ['target_id' => $node->id()], array_values($project->get($field)->referencedEntities())));
        if (count($project->validate())) {
          throw new UnprocessableEntityHttpException('Invalid project relationship.');
        }
        $project->save();
      }
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

  /** Reject stale JSON:API/entity saves instead of overwriting a confirmed append. */
  public function guardSave(NodeInterface $node): void {
    $field = $this->resources->get('node', 'project')?->getInternalName('field_conversations');
    if ($node->bundle() !== 'project' || !$field || !$node->hasField($field)) {
      return;
    }
    if (!$node->isNew()) {
      $vid = $this->database->select('node', 'n')->fields('n', ['vid'])
        ->condition('nid', $node->id())->forUpdate()->execute()->fetchField();
      if (!$vid || (string) $vid !== (string) $node->getLoadedRevisionId()) {
        throw new ConversationReferenceConflict();
      }
      // Every writer advances the revision, including ordinary relationship POST requests.
      $node->setNewRevision(TRUE);
      $node->setRevisionUserId($this->account->id());
      $node->setRevisionCreationTime($this->time->getRequestTime());
    }
    $this->references->guardSave($node, $field);
  }

}

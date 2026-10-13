<?php

declare(strict_types=1);

namespace Drupal\xinshi_entity_tools;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_entity_tools\Entity\EntityToolResource;

/** Access-checked node operations, independent of the AI transport and task store. */
final class EntityService {

  private const TABLE = 'xinshi_entity_tool_operation';

  public function __construct(
    private readonly ResourceCatalog $resources,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly Connection $database,
    private readonly PrivateKey $privateKey,
    private readonly ?ModerationInformationInterface $moderation,
    private readonly ?StateTransitionValidationInterface $transitions,
  ) {}

  /** Queries a bounded ID page and checks field access before evaluating predicates. */
  public function query(\stdClass $input): array {
    EntityInput::resource($input, ['fields', 'filters', 'limit'], ['after']);
    EntityInput::fields($input->fields);
    if (!is_array($input->filters) || !array_is_list($input->filters) || count($input->filters) > 5 ||
      !is_int($input->limit) || $input->limit < 1 || $input->limit > 25) {
      throw new EntityToolException('invalid_input', 400);
    }
    if (property_exists($input, 'after')) {
      EntityInput::id($input->after);
    }
    $required = $input->fields;
    foreach ($input->filters as $filter) {
      if (!$filter instanceof \stdClass || count(get_object_vars($filter)) !== 3 ||
        !is_string($filter->field ?? NULL) || !in_array($filter->operator ?? NULL, ['eq', 'contains'], TRUE) ||
        !property_exists($filter, 'value') || !is_scalar($filter->value) || is_float($filter->value) ||
        (is_string($filter->value) && strlen($filter->value) > 1024) ||
        ($filter->operator === 'contains' && (!is_string($filter->value) || $filter->value === ''))) {
        throw new EntityToolException('invalid_input', 400);
      }
      $required[] = $filter->field;
    }
    $resource = $this->resource($input);
    $this->allowedFields($resource, $required);
    $this->resources->validateFilters($resource, $input->filters);
    $storage = $this->entities->getStorage('node');
    $ids = array_values($storage->getQuery()->accessCheck(TRUE)
      ->condition('type', $resource->get('bundle'))->condition('nid', $input->after ?? '0', '>')
      ->sort('nid')->range(0, 501)->execute());
    $items = [];
    $after = NULL;
    foreach (array_chunk(array_slice($ids, 0, 500), 50) as $batch) {
      foreach ($storage->loadMultiple($batch) as $node) {
        $after = (string) $node->id();
        try {
          $node = $this->translation($node, $input->language);
          $values = $this->values($node, array_values(array_unique($required)));
        }
        catch (EntityToolException $error) {
          if (in_array($error->reason, ['entity_unavailable', 'field_denied'], TRUE)) {
            continue;
          }
          throw $error;
        }
        foreach ($input->filters as $filter) {
          $value = $values[$filter->field];
          if (is_array($value)) {
            $value = $value['value'];
          }
          if ($filter->operator === 'eq' ? $value !== $filter->value :
            !is_string($value) || !str_contains(mb_strtolower($value), mb_strtolower($filter->value))) {
            continue 2;
          }
        }
        $items[] = $this->snapshot($node) + ['values' => (object) array_intersect_key($values, array_flip($input->fields))];
        if (count($items) === $input->limit) {
          break 2;
        }
      }
    }
    $more = $after !== NULL && (string) end($ids) !== $after;
    return $this->bounded(['query' => $input, 'items' => $items, 'nextAfter' => $more ? $after : NULL]);
  }

  /** Reads the default revision in the exact requested translation. */
  public function read(\stdClass $input): array {
    EntityInput::resource($input, ['entityId', 'fields']);
    EntityInput::id($input->entityId);
    EntityInput::fields($input->fields);
    $resource = $this->resource($input);
    $this->allowedFields($resource, $input->fields);
    $node = $this->load($resource, $input->entityId, $input->language);
    return $this->bounded(['query' => $input, 'entity' => $this->snapshot($node) +
      ['values' => (object) $this->values($node, $input->fields)]]);
  }

  /** Performs actual Typed Data validation without saving an entity or receipt. */
  public function validate(\stdClass $input): array {
    EntityInput::mutation($input);
    [$node, $errors] = $this->prepare($input);
    return ['valid' => !$errors, 'errors' => $errors,
      'target' => ['id' => $node->isNew() ? NULL : (string) $node->id(),
        'label' => mb_substr((string) $node->label(), 0, 255)]];
  }

  /** Commits the approved patch and its receipt atomically under one execution ID. */
  public function write(string $executionId, \stdClass $input): array {
    $this->executionId($executionId);
    if ($existing = $this->operation($executionId)) {
      return $this->receipt($existing, $input);
    }
    $transaction = $this->database->startTransaction();
    $reserved = FALSE;
    try {
      EntityInput::mutation($input);
      $this->database->insert(self::TABLE)->fields([
        'execution_id' => $executionId, 'uid' => $this->account->id(),
        'input_json' => EntityInput::encode($input), 'created' => time(),
      ])->execute();
      $reserved = TRUE;
      if ($input->operation === 'update') {
        $this->database->select('node', 'n')->fields('n', ['nid'])
          ->condition('nid', $input->entityId)->forUpdate()->execute()->fetchField();
        $this->entities->getStorage('node')->resetCache([$input->entityId]);
      }
      [$node, $errors] = $this->prepare($input);
      if ($errors) {
        throw new EntityToolException('validation_failed');
      }
      $node->setNewRevision(TRUE);
      $node->setRevisionUserId($this->account->id());
      $node->setRevisionCreationTime(time());
      $node->setRevisionLogMessage('Entity tool draft operation: ' . $executionId);
      $node->save();
      // Hooks may change publication state. Reject that change inside the same transaction.
      $saved = $this->entities->getStorage('node')->loadUnchanged($node->id());
      if (!$saved || $saved->isPublished() || !$this->canRead($saved) ||
        (string) $saved->getRevisionId() !== (string) $node->getRevisionId()) {
        throw new EntityToolException('draft_not_supported');
      }
      $this->values($saved, array_keys(get_object_vars($input->values)));
      $result = $this->snapshot($saved);
      $this->database->update(self::TABLE)->fields([
        'nid' => $saved->id(), 'result_json' => json_encode($result, JSON_THROW_ON_ERROR),
      ])->condition('execution_id', $executionId)->execute();
      unset($transaction);
      return ['executionId' => $executionId, 'input' => $input, 'result' => $result];
    }
    catch (\Throwable $error) {
      if (isset($transaction)) {
        $transaction->rollBack();
        unset($transaction);
      }
      $this->entities->getStorage('node')->resetCache();
      if (!$reserved && $error instanceof IntegrityConstraintViolationException &&
        ($existing = $this->operation($executionId))) {
        return $this->receipt($existing, $input);
      }
      if ($error instanceof EntityToolException) {
        throw new EntityToolException($error->reason, $error->status, TRUE);
      }
      throw $error;
    }
  }

  /** Reconciles only a saved operation, including after its resource is disabled. */
  public function find(string $executionId): ?array {
    $this->executionId($executionId);
    $operation = $this->operation($executionId);
    return $operation ? $this->receipt($operation) : NULL;
  }

  private function resource(\stdClass $input): EntityToolResource {
    $resource = $this->resources->get($input->resourceId, $input->resourceVersion);
    if (!in_array($input->language, $resource->get('languages'), TRUE)) {
      throw new EntityToolException('language_unsupported');
    }
    return $resource;
  }

  private function allowedFields(EntityToolResource $resource, array $fields, bool $write = FALSE): void {
    if (array_diff($fields, $resource->get($write ? 'write_fields' : 'fields'))) {
      throw new EntityToolException('field_denied', 403);
    }
  }

  private function load(EntityToolResource $resource, string $id, string $language): NodeInterface {
    $node = $this->entities->getStorage('node')->loadUnchanged($id);
    if (!$node || $node->bundle() !== $resource->get('bundle')) {
      throw new EntityToolException('entity_unavailable', 404);
    }
    return $this->translation($node, $language);
  }

  private function translation(NodeInterface $node, string $language): NodeInterface {
    if (!$node->hasTranslation($language) || !$node->access('view', $this->account)) {
      throw new EntityToolException('entity_unavailable', 404);
    }
    $translation = $node->getTranslation($language);
    if (!$this->canRead($translation)) {
      throw new EntityToolException('entity_unavailable', 404);
    }
    return $translation;
  }

  private function canRead(NodeInterface $node): bool {
    if (!$node->access('view', $this->account)) {
      return FALSE;
    }
    foreach (['title', 'nid', 'uuid', 'vid', 'langcode', 'status'] as $name) {
      if (!$node->get($name)->access('view', $this->account)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function values(NodeInterface $node, array $fields): array {
    $values = [];
    foreach ($fields as $name) {
      $field = $node->get($name);
      if (!$field->access('view', $this->account)) {
        throw new EntityToolException('field_denied', 403);
      }
      $type = $field->getFieldDefinition()->getType();
      $value = $field->value;
      $values[$name] = $field->isEmpty() ? NULL : match ($type) {
        'integer', 'list_integer', 'timestamp' => (int) $value,
        'boolean' => (bool) $value,
        'text', 'text_long' => ['value' => (string) $value],
        'text_with_summary' => ['value' => (string) $value, 'summary' => (string) $field->summary],
        default => (string) $value,
      };
    }
    return $values;
  }

  private function prepare(\stdClass $input): array {
    $resource = $this->resource($input);
    if (!$this->account->hasPermission('write entity tools') ||
      !$resource->get($input->operation === 'create' ? 'allow_create' : 'allow_update')) {
      throw new EntityToolException('permission_denied', 403);
    }
    $this->allowedFields($resource, array_keys(get_object_vars($input->values)), TRUE);
    $storage = $this->entities->getStorage('node');
    if ($input->operation === 'create') {
      if (!$this->entities->getAccessControlHandler('node')->createAccess($resource->get('bundle'), $this->account)) {
        throw new EntityToolException('permission_denied', 403);
      }
      $node = $storage->create(['type' => $resource->get('bundle'), 'uid' => $this->account->id(),
        'langcode' => $input->language, 'status' => 0]);
    }
    else {
      $node = $this->load($resource, $input->entityId, $input->language);
      if (!$node->access('update', $this->account)) {
        throw new EntityToolException('permission_denied', 403);
      }
      if (!hash_equals($this->version($node), $input->expectedVersion)) {
        throw new EntityToolException('version_conflict', 409);
      }
      if ($node->isPublished() || count($node->getTranslationLanguages()) !== 1 ||
        !$node->isDefaultTranslation() ||
        (string) $storage->getLatestRevisionId($node->id()) !== (string) $node->getRevisionId()) {
        throw new EntityToolException('draft_not_supported');
      }
      $node = clone $node;
    }
    $this->draftState($node);
    foreach (get_object_vars($input->values) as $name => $value) {
      $field = $node->get($name);
      if (!$field->access('view', $this->account) || !$field->access('edit', $this->account)) {
        throw new EntityToolException('field_denied', 403);
      }
      $type = $field->getFieldDefinition()->getType();
      if ($value === NULL) {
        $field->setValue([]);
        continue;
      }
      $valid = match ($type) {
        'integer', 'list_integer', 'timestamp' => is_int($value) && abs($value) <= 9007199254740991,
        'boolean' => is_bool($value),
        'text', 'text_long', 'text_with_summary' => $value instanceof \stdClass &&
          !array_diff(array_keys(get_object_vars($value)), $type === 'text_with_summary' ? ['value', 'summary'] : ['value']) &&
          is_string($value->value ?? NULL) && strlen($value->value) <= 65536 &&
          (!property_exists($value, 'summary') || is_string($value->summary) && strlen($value->summary) <= 8192),
        default => is_string($value) && strlen($value) <= 65536,
      };
      if (!$valid) {
        throw new EntityToolException('invalid_input', 400);
      }
      if (str_starts_with($type, 'text')) {
        $format = $this->entities->getStorage('filter_format')->load($resource->get('text_format'));
        if (!$format || !$format->status() || !$format->access('use', $this->account)) {
          throw new EntityToolException('field_denied', 403);
        }
        $field->setValue((array) $value + ['format' => $format->id()]);
      }
      else {
        $field->setValue(['value' => $value]);
      }
    }
    $errors = [];
    foreach ($node->validate() as $violation) {
      $field = explode('.', (string) $violation->getPropertyPath())[0];
      $readable = in_array($field, $resource->get('fields'), TRUE) &&
        $node->get($field)->access('view', $this->account);
      $errors[] = ['field' => $readable ? $field : NULL, 'code' => 'constraint_violation',
        'message' => $readable ? mb_substr((string) $violation->getMessage(), 0, 500) : 'Entity validation failed.'];
      if (count($errors) === 50) {
        break;
      }
    }
    if (!$this->canRead($node)) {
      throw new EntityToolException('permission_denied', 403);
    }
    return [$node, $errors];
  }

  private function draftState(NodeInterface $node): void {
    $node->setUnpublished();
    if (!$this->moderation || !$this->moderation->isModeratedEntity($node)) {
      return;
    }
    $workflow = $this->moderation->getWorkflowForEntity($node);
    $type = $workflow->getTypePlugin();
    $original = $this->moderation->getOriginalState($node);
    $state = $node->isNew() ? $type->getInitialState($node) : $original;
    if ($state->isPublishedState() || !$this->transitions || !$original->canTransitionTo($state->id()) ||
      !$this->transitions->isTransitionValid($workflow, $original, $state, $this->account, $node)) {
      throw new EntityToolException('draft_not_supported');
    }
    $node->set('moderation_state', $state->id());
  }

  private function snapshot(NodeInterface $node): array {
    return ['id' => (string) $node->id(), 'uuid' => $node->uuid(),
      'revisionId' => (string) $node->getRevisionId(), 'version' => $this->version($node),
      'language' => $node->language()->getId(), 'status' => $node->isPublished() ? 'published' : 'draft',
      'url' => $node->toUrl('canonical')->toString()];
  }

  private function version(NodeInterface $node): string {
    $value = [$node->getUntranslated()->toArray(),
      $this->entities->getStorage('node')->getLatestRevisionId($node->id())];
    return hash_hmac('sha256', json_encode($value, JSON_THROW_ON_ERROR), $this->privateKey->get());
  }

  private function executionId(string $executionId): void {
    if (!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/Di', $executionId)) {
      throw new EntityToolException('invalid_input', 400);
    }
    if (!$this->account->isAuthenticated() ||
      !$this->entities->getStorage('user')->loadUnchanged($this->account->id())?->isActive()) {
      throw new EntityToolException('permission_denied', 403);
    }
  }

  private function operation(string $executionId): ?object {
    return $this->database->select(self::TABLE, 'o')->fields('o')
      ->condition('execution_id', $executionId)->execute()->fetchObject() ?: NULL;
  }

  private function receipt(object $operation, ?\stdClass $input = NULL): array {
    if ((string) $operation->uid !== (string) $this->account->id()) {
      throw new EntityToolException('permission_denied', 403);
    }
    if ($input !== NULL && $operation->input_json !== EntityInput::encode($input)) {
      throw new EntityToolException('version_conflict', 409);
    }
    $savedInput = json_decode($operation->input_json, FALSE, 16, JSON_THROW_ON_ERROR);
    $node = $this->entities->getStorage('node')->loadUnchanged($operation->nid);
    if (!$node) {
      throw new EntityToolException('entity_unavailable', 404);
    }
    $node = $this->translation($node, $savedInput->language);
    // Reconciliation does not need current write permission, but cannot reveal withdrawn fields.
    $this->values($node, array_keys(get_object_vars($savedInput->values)));
    return ['executionId' => $operation->execution_id, 'input' => $savedInput,
      'result' => json_decode($operation->result_json, TRUE, 16, JSON_THROW_ON_ERROR)];
  }

  private function bounded(array $value): array {
    if (strlen(json_encode($value, JSON_THROW_ON_ERROR)) > 1048576) {
      throw new EntityToolException('result_too_large');
    }
    return $value;
  }

}

<?php

namespace Drupal\xinshi_api;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\panelizer\PanelizerInterface;
use Drupal\panels\PanelsDisplayManagerInterface;

/** Creates only new, unpublished entities, atomically with their operation record. */
final class PageDraftService {

  private const TABLE = 'xinshi_page_draft_operation';

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
    private readonly LanguageManagerInterface $languages,
    private readonly TimeInterface $time,
    private readonly ?PanelizerInterface $panelizer,
    private readonly ?PanelsDisplayManagerInterface $panels,
    private readonly ?ModerationInformationInterface $moderation,
    private readonly ?StateTransitionValidationInterface $transitions,
  ) {}

  public function canCreate(): bool {
    try {
      if (!$this->database->schema()->tableExists(self::TABLE)) {
        return FALSE;
      }
      $this->prepareEntities((object) ['title' => 'Draft', 'body' => [(object) ['type' => 'text']]]);
      return TRUE;
    }
    catch (\Throwable $e) {
      return FALSE;
    }
  }

  public function createDraft(string $execution_id, mixed $input): array {
    $this->validateId($execution_id);
    $input = $this->validateInput($input);
    $encoded = $this->encodeInput($input);
    if ($existing = $this->operation($execution_id)) {
      return $this->existingResult($existing, $encoded);
    }

    $transaction = $this->database->startTransaction();
    $reserved = FALSE;
    try {
      // The unique key serializes concurrent POSTs; the reservation is never committed alone.
      $this->database->insert(self::TABLE)->fields([
        'execution_id' => $execution_id,
        'uid' => $this->account->id(),
        'input_json' => $encoded,
        'created' => $this->time->getRequestTime(),
      ])->execute();
      $reserved = TRUE;
      [$node, $block] = $this->prepareEntities($input);
      if (count($node->validate()) || count($block->validate())) {
        throw new PageDraftException('invalid_input', 422);
      }
      $block->save();
      $display = $this->panels->createDisplay('layout_onecol', 'ipe');
      $display->setConfiguration(array_replace($display->getConfiguration(), [
        'page_title' => '[node:title]',
        'pattern' => 'panelizer',
      ]));
      $regions = $display->getLayout()->getPluginDefinition()->get('regions');
      $display->addBlock([
        'id' => 'block_content:' . $block->uuid(),
        'label' => $block->label(),
        'label_display' => 0,
        'region' => array_key_first($regions),
        'weight' => 1,
        'vid' => $block->getRevisionId(),
      ]);
      $this->panelizer->setPanelsDisplay($node, 'full', '__bundle_default__', $display);
      if ($node->isPublished() || $block->isPublished()) {
        throw new \RuntimeException('Draft entities must remain unpublished.');
      }
      if (!$node->access('view', $this->account)) {
        throw new PageDraftException('permission_denied', 403);
      }
      $result = [
        'id' => (string) $node->id(),
        'url' => $node->toUrl('canonical', ['absolute' => FALSE])->toString(),
        'status' => 'draft',
      ];
      $this->database->update(self::TABLE)->fields([
        'nid' => $node->id(),
        'result_json' => json_encode($result, JSON_THROW_ON_ERROR),
      ])->condition('execution_id', $execution_id)->execute();
      // Drupal commits when the transaction object is released. Do not report success earlier.
      unset($transaction);
      return ['executionId' => $execution_id, 'input' => $input, 'result' => $result];
    }
    catch (\Throwable $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
        unset($transaction);
      }
      $this->entities->getStorage('node')->resetCache();
      $this->entities->getStorage('block_content')->resetCache();
      if (!$reserved && $e instanceof IntegrityConstraintViolationException) {
        // A concurrent winner has committed by the time the duplicate insert fails.
        if ($existing = $this->operation($execution_id)) {
          return $this->existingResult($existing, $encoded);
        }
      }
      throw $e;
    }
  }

  public function findDraft(string $execution_id): ?array {
    $this->validateId($execution_id);
    $operation = $this->operation($execution_id);
    return $operation ? $this->existingResult($operation) : NULL;
  }

  private function operation(string $execution_id): ?object {
    return $this->database->select(self::TABLE, 'op')->fields('op')
      ->condition('execution_id', $execution_id)->execute()->fetchObject() ?: NULL;
  }

  private function existingResult(object $operation, ?string $input = NULL): array {
    if ((string) $operation->uid !== (string) $this->account->id()) {
      throw new PageDraftException('not_found', 404);
    }
    if ($input !== NULL && $operation->input_json !== $input) {
      throw new PageDraftException('operation_conflict', 409);
    }
    $node = $this->entities->getStorage('node')->load($operation->nid);
    if (!$node || !$node->access('view', $this->account)) {
      throw new PageDraftException('result_inaccessible', 403);
    }
    return [
      'executionId' => $operation->execution_id,
      'input' => json_decode($operation->input_json, FALSE, 64, JSON_THROW_ON_ERROR),
      // Return the original result even if the page title or alias changed later.
      'result' => json_decode($operation->result_json, TRUE, 64, JSON_THROW_ON_ERROR),
    ];
  }

  private function prepareEntities(\stdClass $input): array {
    if (!$this->account->isAuthenticated() || !$this->panelizer || !$this->panels ||
      !$this->entities->getAccessControlHandler('node')->createAccess('landing_page', $this->account) ||
      !$this->entities->getAccessControlHandler('block_content')->createAccess('json', $this->account)) {
      throw new PageDraftException('permission_denied', 403);
    }
    $format = $this->entities->getStorage('filter_format')->load('json');
    if (!$format || !$format->access('use', $this->account)) {
      throw new PageDraftException('permission_denied', 403);
    }
    $langcode = $input->langcode ?? $this->languages->getDefaultLanguage()->getId();
    if (!$this->languages->getLanguage($langcode)) {
      throw new PageDraftException('invalid_input', 422);
    }
    $node = $this->entities->getStorage('node')->create([
      'type' => 'landing_page', 'title' => $input->title,
      'uid' => $this->account->id(), 'langcode' => $langcode, 'status' => FALSE,
    ]);
    $settings = $this->panelizer->getPanelizerSettings('node', 'landing_page', 'full');
    if (!$node->hasField('panelizer') || !($settings['custom'] || $settings['allow'])) {
      throw new PageDraftException('draft_not_supported', 503);
    }
    // Model-supplied UUIDs remain plain component data; existing blocks are never loaded/updated.
    $block = $this->entities->getStorage('block_content')->create([
      'type' => 'json', 'info' => $input->title, 'langcode' => $langcode, 'status' => FALSE,
      'body' => [
        'value' => json_encode($input->body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        'format' => 'json',
      ],
    ]);
    $this->setDraftState($node);
    $this->setDraftState($block);
    if (!$node->access('view', $this->account)) {
      throw new PageDraftException('permission_denied', 403);
    }
    return [$node, $block];
  }

  private function setDraftState(ContentEntityInterface $entity): void {
    if (!$this->moderation || !$this->moderation->isModeratedEntity($entity)) {
      return;
    }
    $workflow = $this->moderation->getWorkflowForEntity($entity);
    $type = $workflow->getTypePlugin();
    if (!$type->hasState('draft') || $type->getState('draft')->isPublishedState()) {
      throw new PageDraftException('draft_not_supported', 503);
    }
    $initial = $type->getInitialState($entity);
    if (!$initial->canTransitionTo('draft') || !$this->transitions->isTransitionValid(
      $workflow, $initial, $type->getState('draft'), $this->account, $entity
    )) {
      throw new PageDraftException('permission_denied', 403);
    }
    $entity->set('moderation_state', 'draft');
  }

  private function validateId(string $execution_id): void {
    if (!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $execution_id)) {
      throw new PageDraftException('invalid_input', 422);
    }
  }

  private function validateInput(mixed $input): \stdClass {
    if (!$input instanceof \stdClass ||
      array_diff(array_keys(get_object_vars($input)), ['title', 'body', 'langcode']) ||
      !isset($input->title) || !is_string($input->title) || !trim($input->title) ||
      mb_strlen($input->title) > 255 || !isset($input->body) || !is_array($input->body) ||
      !array_is_list($input->body) || !count($input->body) || count($input->body) > 200) {
      throw new PageDraftException('invalid_input', 422);
    }
    foreach ($input->body as $component) {
      if (!$component instanceof \stdClass || !isset($component->type) ||
        !is_string($component->type) || !trim($component->type)) {
        throw new PageDraftException('invalid_input', 422);
      }
    }
    if (property_exists($input, 'langcode') && (!is_string($input->langcode) ||
      !preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/iD', $input->langcode))) {
      throw new PageDraftException('invalid_input', 422);
    }
    return $input;
  }

  private function encodeInput(\stdClass $input): string {
    $sort = function ($value) use (&$sort) {
      if ($value instanceof \stdClass) {
        $properties = get_object_vars($value);
        ksort($properties);
        return (object) array_map($sort, $properties);
      }
      return is_array($value) ? array_map($sort, $value) : $value;
    };
    return json_encode($sort($input), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

}

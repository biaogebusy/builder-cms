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
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Drupal\node\NodeInterface;

/** Creates, appends to and deletes owned drafts atomically with their operation record. */
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

  public function capabilities(): array {
    $permissions = $this->canCreate() ? ['pages.create_draft'] : [];
    if (!$this->account->isAuthenticated() || !$this->database->schema()->tableExists(self::TABLE)) {
      return $permissions;
    }
    $node = $this->entities->getStorage('node')->create([
      'type' => 'landing_page', 'title' => 'Draft', 'uid' => $this->account->id(),
      'langcode' => $this->languages->getDefaultLanguage()->getId(), 'status' => FALSE,
    ]);
    if ($node->access('view', $this->account)) {
      $permissions[] = 'pages.read_draft';
      $format = $this->entities->getStorage('filter_format')->load('json');
      if ($node->access('update', $this->account) && $format &&
        $format->access('use', $this->account) &&
        $this->entities->getAccessControlHandler('block_content')->createAccess('json', $this->account)) {
        $permissions[] = 'pages.update_draft';
      }
      if ($node->access('delete', $this->account)) {
        $permissions[] = 'pages.delete_draft';
      }
    }
    return $permissions;
  }

  public function createDraft(string $execution_id, mixed $input): array {
    $this->validateId($execution_id);
    $input = $this->validateInput($input);
    return $this->writeOperation($execution_id, $input, function () use ($input) {
      [$node, $blocks] = $this->prepareEntities($input);
      $this->saveComponents($node, $blocks, NULL);
      return $this->pageResult($node);
    });
  }

  public function readDraft(string $page_id): array {
    return $this->snapshot($this->ownedDraft($page_id, 'view'));
  }

  public function changeDraft(string $execution_id, mixed $input): array {
    $this->validateId($execution_id);
    $input = $this->validateChange($input);
    return $this->writeOperation($execution_id, $input, function () use ($input) {
      // Serialize writers for this page, including requests with different operation IDs.
      $this->database->select('node', 'n')->fields('n', ['nid'])
        ->condition('nid', $input->pageId)->forUpdate()->execute()->fetchField();
      $node = $this->ownedDraft($input->pageId, $input->action === 'append' ? 'update' : 'delete');
      $current = $this->snapshot($node);
      if (!hash_equals($current['version'], $input->expectedVersion)) {
        throw new PageDraftException('version_conflict', 409);
      }
      if ($input->action === 'delete') {
        $node->delete();
        return ['id' => $input->pageId, 'status' => 'deleted'];
      }
      $body = [...$current['body'], ...$input->body];
      if (count($body) > 200 || strlen(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) > 1048576) {
        throw new PageDraftException('invalid_input', 422);
      }
      $this->setDraftState($node);
      $blocks = $this->prepareBlocks((object) [
        'title' => $node->label(), 'body' => $input->body,
      ], $node->language()->getId());
      $node->setNewRevision(TRUE);
      $display = $this->panels->importDisplay(
        $this->panelizer->getPanelsDisplay($node, 'full')->getConfiguration(), FALSE);
      $this->saveComponents($node, $blocks, $display);
      return $this->pageResult($node);
    });
  }

  private function writeOperation(string $execution_id, \stdClass $input, callable $write): array {
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
      $result = $write();
      $this->database->update(self::TABLE)->fields([
        'nid' => $result['id'],
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

  private function saveComponents(NodeInterface $node, array $blocks, ?PanelsDisplayVariant $display): void {
    foreach ([$node, ...$blocks] as $entity) {
      if (count($entity->validate())) {
        throw new PageDraftException('invalid_input', 422);
      }
    }
    if (!$display) {
      $display = $this->panels->createDisplay('layout_onecol', 'ipe');
      $display->setConfiguration(array_replace($display->getConfiguration(), [
        'page_title' => '[node:title]', 'pattern' => 'panelizer',
      ]));
    }
    $regions = $display->getLayout()->getPluginDefinition()->get('regions');
    $existing = $display->getConfiguration()['blocks'] ?? [];
    $weight = $existing ? max(array_map(fn($block) => (int) ($block['weight'] ?? 0), $existing)) + 1 : 0;
    foreach ($blocks as $block) {
      $block->save();
      $display->addBlock([
        'id' => 'block_content:' . $block->uuid(), 'label' => $block->label(),
        'label_display' => 0, 'region' => array_key_last($regions),
        'weight' => $weight++, 'vid' => $block->getRevisionId(),
      ]);
    }
    $this->panelizer->setPanelsDisplay($node, 'full', '__bundle_default__', $display);
    foreach ([$node, ...$blocks] as $entity) {
      if ($entity->isPublished()) {
        throw new \RuntimeException('Draft entities must remain unpublished.');
      }
    }
    if (!$node->access('view', $this->account)) {
      throw new PageDraftException('permission_denied', 403);
    }
  }

  private function pageResult(NodeInterface $node): array {
    return ['id' => (string) $node->id(),
      'url' => $node->toUrl('canonical', ['absolute' => FALSE])->toString(), 'status' => 'draft'];
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
    $result = json_decode($operation->result_json, TRUE, 64, JSON_THROW_ON_ERROR);
    // A deletion receipt remains readable by its actor after the node is gone or rights change.
    if (($result['status'] ?? NULL) !== 'deleted') {
      $node = $this->entities->getStorage('node')->load($operation->nid);
      if (!$node || !$node->access('view', $this->account)) {
        throw new PageDraftException('result_inaccessible', 403);
      }
    }
    return [
      'executionId' => $operation->execution_id,
      'input' => json_decode($operation->input_json, FALSE, 64, JSON_THROW_ON_ERROR),
      // Return the original result even if the page title or alias changed later.
      'result' => $result,
    ];
  }

  private function ownedDraft(string $page_id, string $operation): NodeInterface {
    if (!preg_match('/^[1-9][0-9]*$/D', $page_id)) {
      throw new PageDraftException('invalid_input', 422);
    }
    if (!$this->account->isAuthenticated()) {
      throw new PageDraftException('permission_denied', 403);
    }
    $storage = $this->entities->getStorage('node');
    $storage->resetCache([$page_id]);
    $node = $storage->load($page_id);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'landing_page') {
      throw new PageDraftException('page_not_found', 404);
    }
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      $translation = $node->getTranslation($langcode);
      if ((string) $translation->getOwnerId() !== (string) $this->account->id() ||
        !$translation->access('view', $this->account) ||
        !$translation->access($operation, $this->account)) {
        throw new PageDraftException('permission_denied', 403);
      }
      if ($translation->isPublished()) {
        throw new PageDraftException('not_draft', 409);
      }
    }
    if ((string) $storage->getLatestRevisionId($page_id) !== (string) $node->getRevisionId()) {
      throw new PageDraftException('version_conflict', 409);
    }
    if (!$this->panelizer || !$this->panels || !$node->hasField('panelizer')) {
      throw new PageDraftException('draft_not_supported', 409);
    }
    return $node;
  }

  private function snapshot(NodeInterface $node): array {
    $body = [];
    $version = [];
    $storage = $this->entities->getStorage('block_content');
    $storage->resetCache();
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      $translation = $node->getTranslation($langcode);
      $layout = $this->panelizer->getPanelsDisplay($translation, 'full')->getConfiguration();
      $contents = [];
      $components = $layout['blocks'] ?? [];
      uasort($components, static fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
      foreach ($components as $configuration) {
        $plugin = $configuration['id'] ?? '';
        if (!str_starts_with($plugin, 'block_content:')) {
          throw new PageDraftException('draft_not_supported', 409);
        }
        $matches = $storage->loadByProperties(['uuid' => substr($plugin, 14)]);
        $block = reset($matches);
        if (!$block || $block->bundle() !== 'json') {
          throw new PageDraftException('draft_not_supported', 409);
        }
        $block = $block->hasTranslation($langcode) ? $block->getTranslation($langcode) : $block;
        $contents[] = $block->toArray();
        if ($langcode === $node->language()->getId()) {
          $component_body = json_decode($block->get('body')->value, FALSE, 64, JSON_THROW_ON_ERROR);
          if (!$component_body instanceof \stdClass || !isset($component_body->type)) {
            throw new PageDraftException('draft_not_supported', 409);
          }
          $body[] = $component_body;
        }
      }
      $values = $translation->toArray();
      $values['panelizer'] = $layout;
      $version[$langcode] = [$values, $contents];
    }
    return $this->pageResult($node) + [
      'title' => $node->label(), 'langcode' => $node->language()->getId(), 'body' => $body,
      // Content participates too: legacy editors can update blocks without changing node vid.
      'version' => hash('sha256', json_encode($version, JSON_THROW_ON_ERROR)),
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
    $this->setDraftState($node);
    $blocks = $this->prepareBlocks($input, $langcode);
    if (!$node->access('view', $this->account)) {
      throw new PageDraftException('permission_denied', 403);
    }
    return [$node, $blocks];
  }

  private function prepareBlocks(\stdClass $input, string $langcode): array {
    $format = $this->entities->getStorage('filter_format')->load('json');
    if (!$this->entities->getAccessControlHandler('block_content')->createAccess('json', $this->account) ||
      !$format || !$format->access('use', $this->account)) {
      throw new PageDraftException('permission_denied', 403);
    }
    $blocks = [];
    // The page reader expects one component object per JSON block, not the body array.
    // Model-supplied UUIDs remain plain component data; existing blocks are never loaded/updated.
    foreach ($input->body as $component) {
      $block = $this->entities->getStorage('block_content')->create([
        'type' => 'json', 'info' => $input->title, 'langcode' => $langcode, 'status' => FALSE,
        'body' => [
          'value' => json_encode($component, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
          'format' => 'json',
        ],
      ]);
      $this->setDraftState($block);
      $blocks[] = $block;
    }
    return $blocks;
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
    $initial = $entity->isNew() ? $type->getInitialState($entity)
      : $type->getState($entity->get('moderation_state')->value);
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

  private function validateChange(mixed $input): \stdClass {
    if (!$input instanceof \stdClass || !isset($input->action, $input->pageId, $input->expectedVersion) ||
      !in_array($input->action, ['append', 'delete'], TRUE) ||
      !is_string($input->pageId) || !preg_match('/^[1-9][0-9]*$/D', $input->pageId) ||
      !is_string($input->expectedVersion) || !preg_match('/^[a-f0-9]{64}$/D', $input->expectedVersion) ||
      array_diff(array_keys(get_object_vars($input)), $input->action === 'append'
        ? ['action', 'pageId', 'expectedVersion', 'body'] : ['action', 'pageId', 'expectedVersion'])) {
      throw new PageDraftException('invalid_input', 422);
    }
    if ($input->action === 'append') {
      $this->validateInput((object) ['title' => 'Draft', 'body' => $input->body ?? NULL]);
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

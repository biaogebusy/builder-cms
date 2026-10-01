<?php

namespace Drupal\xinshi_api;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;

/** Authorizes and saves the existing builder protocol in one transaction. */
class PageWriteService {

  private const LAYOUT = OverridesSectionStorage::FIELD_NAME;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly LanguageManagerInterface $languages,
    private readonly UuidInterface $uuid,
    private readonly PageModerationPolicy $moderation,
    private readonly ?ContentTranslationManagerInterface $translations,
    private readonly InlineBlockUsageInterface $usage,
  ) {}

  public function createPage(array $input): NodeInterface {
    $this->validateInput($input);
    $this->requireCreate('node', 'landing_page');
    return $this->atomic(function () use ($input) {
      $node = $this->entities->getStorage('node')->create([
        'type' => 'landing_page', 'title' => $input['title'],
        'uid' => $this->account->id(), 'status' => TRUE,
        'langcode' => $this->languages->getCurrentLanguage()->getId(),
      ]);
      $this->requireLayout($node);
      $this->requireFields($node, ['title']);
      $this->moderation->apply($node, publish: TRUE);
      // Keep UUID-based sharing; normal frontend copies omit the outer block UUID.
      $blocks = $this->prepareBlocks($node, $input['body']);
      return $this->save($node, $blocks);
    });
  }

  public function updatePage(NodeInterface $route_node, array $input): NodeInterface {
    $this->validateInput($input);
    $vid = $this->revisionId($input['vid'] ?? NULL);
    return $this->atomic(function () use ($route_node, $input, $vid) {
      $node = $this->currentNode($route_node);
      $this->requireLayout($node);
      $this->requireFields($node, ['title']);
      $storage = $this->entities->getStorage('node');
      if ((string) $node->getRevisionId() !== $vid) {
        $previous = $storage->loadRevision($vid);
        if (!$previous || $previous->id() != $node->id() || !$previous->hasTranslation($node->language()->getId())) {
          throw new PageDraftException('version_conflict', 409);
        }
        $previous = $previous->getTranslation($node->language()->getId());
        if ($previous->label() !== $node->label() ||
          serialize($previous->get(self::LAYOUT)->getValue()) !== serialize($node->get(self::LAYOUT)->getValue())) {
          throw new PageDraftException('version_conflict', 409);
        }
      }
      $blocks = $this->prepareBlocks($node, $input['body']);
      $node->set('title', $input['title']);
      $this->moderation->apply($node);
      return $this->save($node, $blocks);
    });
  }

  public function translatePage(NodeInterface $route_node, string $source, string $target, array $input): NodeInterface {
    return $this->atomic(function () use ($route_node, $source, $target, $input) {
      $node = $this->currentNode($route_node);
      if (!$this->languages->getLanguage($target) || !$node->hasTranslation($source) || $node->hasTranslation($target)) {
        throw new PageDraftException('invalid_input', 422);
      }
      $this->requireTranslation($node, 'create');
      $source_node = $node->getTranslation($source);
      $this->requireLayout($source_node);
      if (!$source_node->access('view', $this->account)) {
        throw new PageDraftException('permission_denied', 403);
      }
      // A non-translatable layout would also replace the source language's layout.
      if (!$source_node->getFieldDefinition(self::LAYOUT)->isTranslatable()) {
        throw new PageDraftException('translation_not_supported', 422);
      }
      $references = $this->references($source_node);
      $rows = $input['body'] ?? [];
      if ($rows === []) {
        foreach ($references as $block) {
          $block = $block->hasTranslation($source) ? $block->getTranslation($source) : $block;
          if (!$block->access('view', $this->account) || !$block->get('body')->access('view', $this->account)) {
            throw new PageDraftException('permission_denied', 403);
          }
          $rows[] = ['uuid' => $block->uuid(), 'attributes' => ['body' => $block->get('body')->value]];
        }
      }
      $input = ['title' => $input['title'] ?? $source_node->label(), 'body' => $rows];
      $this->validateInput($input, TRUE);
      $translation = $node->addTranslation($target, $source_node->toArray());
      $this->requireFields($translation, ['title', 'path']);
      $translation->set('title', $input['title']);
      $translation->setCreatedTime(time());
      $translation->setChangedTime(time());
      $translation->setOwnerId($this->account->id());
      $translation->set('path', [
        'alias' => $source_node->get('path')->alias ?: '', 'pid' => NULL, 'langcode' => $target,
      ]);
      $blocks = $this->prepareBlocks($translation, $input['body']);
      $this->moderation->apply($translation);
      return $this->save($translation, $blocks);
    });
  }

  private function currentNode(NodeInterface $route_node): NodeInterface {
    // Serialize builder writers before refreshing the default revision and its layout.
    $this->database->select('node', 'n')->fields('n', ['nid'])
      ->condition('nid', $route_node->id())->forUpdate()->execute()->fetchField();
    $storage = $this->entities->getStorage('node');
    $storage->resetCache([$route_node->id()]);
    $node = $storage->load($route_node->id());
    if (!$node || $node->bundle() !== 'landing_page' || !$node->hasTranslation($route_node->language()->getId())) {
      throw new PageDraftException('invalid_input', 422);
    }
    $node = clone $node->getTranslation($route_node->language()->getId());
    if (!$node->access('update', $this->account)) {
      throw new PageDraftException('permission_denied', 403);
    }
    // This protocol edits the default revision; never overwrite a pending revision.
    if ($storage->getLatestRevisionId($node->id()) != $node->getRevisionId()) {
      throw new PageDraftException('version_conflict', 409);
    }
    if (!$node->isDefaultTranslation()) {
      $this->requireTranslation($node, 'update');
    }
    return $node;
  }

  /** Reads source references in layout order when copying a translation's body. */
  private function references(NodeInterface $node): array {
    $result = [];
    $storage = $this->entities->getStorage('block_content');
    foreach ($node->get(self::LAYOUT)->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        $config = $component->get('configuration');
        $plugin = $config['id'] ?? '';
        $shared = str_starts_with($plugin, 'block_content:');
        $inline = str_starts_with($plugin, 'inline_block:');
        if (!$shared && !$inline) {
          continue;
        }
        $revision = $config['block_revision_id'] ?? $config['vid'] ?? NULL;
        if ($revision !== NULL) {
          $block = $storage->loadRevision($this->revisionId($revision));
        }
        elseif ($shared) {
          $matches = $storage->loadByProperties(['uuid' => substr($plugin, 14)]);
          $block = reset($matches);
        }
        else {
          $block = NULL;
        }
        if (!$block || $block->bundle() !== 'json' ||
          ($shared && $block->uuid() !== substr($plugin, 14)) || ($inline && $plugin !== 'inline_block:json')) {
          continue;
        }
        $result[] = $block;
      }
    }
    return $result;
  }

  private function prepareBlocks(NodeInterface $node, array $rows): array {
    $format = $this->entities->getStorage('filter_format')->load('json');
    if (!$format || !$format->access('use', $this->account)) {
      throw new PageDraftException('permission_denied', 403);
    }
    $storage = $this->entities->getStorage('block_content');
    $langcode = $node->language()->getId();
    $blocks = [];
    foreach ($rows as $row) {
      $id = $row['uuid'] ?? '';
      $matches = $id !== '' ? $storage->loadByProperties(['uuid' => $id]) : [];
      $block = $matches ? reset($matches) : NULL;
      if ($block && $block->bundle() !== 'json') {
        continue;
      }
      if ($block) {
        $block = clone $block;
        // Preserve recorded dependencies. Legacy API blocks have no usage record
        // and support sharing; check those in the receiving page's context.
        // Core still requires block edit permissions and honors access hooks.
        if (!$block->isReusable() && !$block->getAccessDependency() && !$this->usage->getUsage($block->id())) {
          $block->setAccessDependency($node);
        }
        if (!$block->access('update', $this->account)) {
          throw new PageDraftException('permission_denied', 403);
        }
        if ($block->hasTranslation($langcode)) {
          $block = $block->getTranslation($langcode);
          if (!$block->isDefaultTranslation()) {
            if (!$block->getFieldDefinition('body')->isTranslatable()) {
              throw new PageDraftException('translation_not_supported', 422);
            }
            $this->requireTranslation($block, 'update');
          }
        }
        elseif ($block->language()->getId() !== $langcode) {
          if (!$block->getFieldDefinition('body')->isTranslatable()) {
            throw new PageDraftException('translation_not_supported', 422);
          }
          $this->requireTranslation($block, 'create');
          $block = $block->addTranslation($langcode, $block->toArray());
        }
        if (!$block->access('update', $this->account)) {
          throw new PageDraftException('permission_denied', 403);
        }
      }
      else {
        $this->requireCreate('block_content', 'json');
        $block = $storage->create([
          'type' => 'json', 'info' => $node->label(), 'langcode' => $langcode, 'reusable' => FALSE,
        ]);
      }
      $this->requireFields($block, ['body', 'reusable']);
      $body = $row['attributes']['body'];
      $block->set('body', ['value' => is_array($body)
        ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) : $body, 'format' => 'json']);
      $block->set('reusable', FALSE);
      $this->moderation->apply($block);
      $blocks[] = $block;
    }
    return $blocks;
  }

  private function save(NodeInterface $node, array $blocks): NodeInterface {
    // Validate every entity before the first save, including fields outside this API.
    foreach ([$node, ...$blocks] as $entity) {
      if (count($entity->validate())) {
        throw new PageDraftException('invalid_input', 422);
      }
    }
    $node->setNewRevision(TRUE);
    $node->setRevisionUserId($this->account->id());
    $sections = [];
    foreach ($blocks as $block) {
      // Preserve shared-block updates: existing pages can pin this revision.
      // Forcing a new block revision would silently leave those pages unchanged.
      $block->save();
      $section = new Section('layout_onecol');
      $section->appendComponent(new SectionComponent($this->uuid->generate(), 'content', [
        'id' => 'inline_block:json', 'label' => $block->label(), 'label_display' => '0',
        'provider' => 'layout_builder', 'view_mode' => 'full', 'block_revision_id' => $block->getRevisionId(),
      ]));
      $sections[] = $section;
    }
    $node->set(self::LAYOUT, $sections);
    $node->save();
    return $node;
  }

  private function requireCreate(string $type, string $bundle): void {
    if (!$this->account->isAuthenticated() || !$this->entities->getAccessControlHandler($type)->createAccess($bundle, $this->account)) {
      throw new PageDraftException('permission_denied', 403);
    }
  }

  private function requireLayout(NodeInterface $node): void {
    // Layout sections are generated by this service after page and block access
    // checks, not accepted as raw field input. Core deliberately forbids direct
    // LayoutSectionItemList field access, including for authorized page authors.
    if (!$node->hasField(self::LAYOUT)) {
      throw new PageDraftException('permission_denied', 403);
    }
  }

  private function requireFields(ContentEntityInterface $entity, array $fields): void {
    foreach ($fields as $name) {
      if (!$entity->hasField($name) || !$entity->get($name)->access('edit', $this->account)) {
        throw new PageDraftException('permission_denied', 403);
      }
    }
  }

  private function requireTranslation(ContentEntityInterface $entity, string $operation): void {
    if (!$this->translations || !$this->translations->isEnabled($entity->getEntityTypeId(), $entity->bundle()) ||
      !$this->entities->getHandler($entity->getEntityTypeId(), 'translation')->getTranslationAccess($entity, $operation)->isAllowed()) {
      throw new PageDraftException('permission_denied', 403);
    }
  }

  private function revisionId(mixed $value): string {
    if ((!is_int($value) && !is_string($value)) || !ctype_digit((string) $value) || (int) $value < 1) {
      throw new PageDraftException('invalid_revision', 422);
    }
    return (string) $value;
  }

  private function validateInput(array $input, bool $allow_empty = FALSE): void {
    if (!is_string($input['title'] ?? NULL) || trim($input['title']) === '' || mb_strlen($input['title']) > 255 ||
      !is_array($input['body'] ?? NULL) || !array_is_list($input['body']) || (!$allow_empty && $input['body'] === [])) {
      throw new PageDraftException('invalid_input', 422);
    }
    foreach ($input['body'] as $row) {
      if (!is_array($row) || ($row['type'] ?? 'json') !== 'json' ||
        (isset($row['uuid']) && !is_string($row['uuid'])) ||
        !is_array($row['attributes'] ?? NULL) || !array_key_exists('body', $row['attributes']) ||
        (!is_array($row['attributes']['body']) && !is_string($row['attributes']['body']))) {
        throw new PageDraftException('invalid_input', 422);
      }
    }
  }

  private function atomic(callable $operation): NodeInterface {
    $transaction = $this->database->startTransaction();
    try {
      $result = $operation();
      unset($transaction);
      return $result;
    }
    catch (\Throwable $error) {
      if (isset($transaction)) {
        $transaction->rollBack();
        unset($transaction);
      }
      $this->entities->getStorage('node')->resetCache();
      $this->entities->getStorage('block_content')->resetCache();
      throw $error;
    }
  }

}

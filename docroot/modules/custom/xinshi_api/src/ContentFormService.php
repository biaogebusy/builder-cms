<?php

declare(strict_types=1);

namespace Drupal\xinshi_api;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\filter\FilterFormatRepositoryInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Projects form metadata using the caller's content and field permissions. */
final class ContentFormService {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly LanguageManagerInterface $languages,
    private readonly FilterFormatRepositoryInterface $formats,
    private readonly ?ResourceTypeRepositoryInterface $resources,
    private readonly ?ModerationInformationInterface $moderation,
    private readonly ?StateTransitionValidationInterface $transitions,
    private readonly ?ContentTranslationManagerInterface $translations,
  ) {}

  /** Lists types the caller can create through the enabled JSON:API resource. */
  public function types(): array {
    $this->requireAccount();
    $types = [];
    foreach ($this->entities->getStorage('node_type')->loadMultiple() as $type) {
      $resource = $this->resources?->get('node', $type->id());
      if ($resource && !$resource->isInternal() &&
        $this->entities->getAccessControlHandler('node')->createAccess($type->id(), $this->account)) {
        $types[] = ['value' => $type->id(), 'label' => $type->label(),
          'desc' => $type->getDescription()];
      }
    }
    return $types;
  }

  /** Describes a new node or a specific editable node translation without saving it. */
  public function node(string $bundle, ?string $uuid, ?string $langcode): array {
    $this->requireAccount();
    if (!$this->entities->getStorage('node_type')->load($bundle)) {
      throw new NotFoundHttpException();
    }
    $resource = $this->resources?->get('node', $bundle);
    if (!$resource || $resource->isInternal()) {
      throw new AccessDeniedHttpException();
    }
    $langcode ??= $this->languages->getDefaultLanguage()->getId();
    if (!$this->languages->getLanguage($langcode)) {
      throw new BadRequestHttpException();
    }
    $node = $this->editableEntity('node', $bundle, $uuid, ['uid' => $this->account->id(),
      'langcode' => $langcode]);
    assert($node instanceof NodeInterface);
    if ($uuid && $node->language()->getId() !== $langcode) {
      if (!$this->translations?->isEnabled('node', $bundle)) {
        throw new AccessDeniedHttpException();
      }
      $operation = $node->hasTranslation($langcode) ? 'update' : 'create';
      if (!$this->entities->getHandler('node', 'translation')
        ->getTranslationAccess($node, $operation)->isAllowed()) {
        throw new AccessDeniedHttpException();
      }
      $node = $node->hasTranslation($langcode) ? $node->getTranslation($langcode) :
        (clone $node)->addTranslation($langcode, $node->toArray());
      if (!$node->access('update', $this->account) || !$node->access('view', $this->account)) {
        throw new AccessDeniedHttpException();
      }
    }
    // An explicit saved display controls presentation, never authorization.
    $display = $this->entities->getStorage('entity_form_display')->load('node.' . $bundle . '.default');
    if (!$display) {
      throw new UnprocessableEntityHttpException('form_display_missing');
    }
    $base_fields = [];
    $read_names = [];
    foreach (['title', 'status', 'moderation_state', 'path', 'revision_log', 'promote', 'sticky', 'langcode'] as $name) {
      if ($node->hasField($name) && $resource->isFieldEnabled($name)) {
        $read_names[$name] = $resource->getPublicName($name);
        if ($this->writable($node->get($name))) {
          $base_fields[$name] = $resource->getPublicName($name);
        }
      }
    }
    $fields = [];
    if ($node->get('title')->isEmpty() && !isset($base_fields['title'])) {
      throw new UnprocessableEntityHttpException('required_field_unavailable');
    }
    foreach ($node->getFieldDefinitions() as $name => $definition) {
      if (!$definition->getFieldStorageDefinition()->isBaseField() && $definition->isRequired() &&
        $node->get($name)->isEmpty() && (!$display->getComponent($name) ||
        !$resource->isFieldEnabled($name) || !$this->writable($node->get($name)))) {
        throw new UnprocessableEntityHttpException('required_field_unavailable');
      }
    }
    $components = $display->getComponents();
    uasort($components, [SortArray::class, 'sortByWeightElement']);
    foreach ($components as $name => $component) {
      if (!$node->hasField($name) || $node->getFieldDefinition($name)->getFieldStorageDefinition()->isBaseField() ||
        !$resource->isFieldEnabled($name) || !$this->writable($node->get($name))) {
        continue;
      }
      $field = $this->describeField($node->get($name), $resource->getPublicName($name));
      if ($field) {
        $fields[] = $field;
      }
    }
    $moderated = $this->moderation?->isModeratedEntity($node) ?? FALSE;
    $states = [];
    $state = $node->isPublished() ? 'published' : 'unpublished';
    if ($moderated) {
      $workflow = $this->moderation->getWorkflowForEntity($node);
      $state = $node->get('moderation_state')->value ?: $workflow->getTypePlugin()->getInitialState($node)->id();
      if (isset($base_fields['moderation_state'])) {
        foreach ($this->transitions?->getValidTransitions($node, $this->account) ?? [] as $transition) {
          $target = $transition->to();
          $states[$target->id()] = ['value' => $target->id(), 'label' => (string) $target->label()];
        }
      }
      unset($base_fields['status']);
    }
    elseif (isset($base_fields['status'])) {
      $states = [['value' => 'published', 'label' => (string) $this->t('Published')],
        ['value' => 'unpublished', 'label' => (string) $this->t('Unpublished')]];
    }
    return ['fields' => $fields, 'baseFields' => (object) $base_fields,
      'baseFieldNames' => (object) $read_names, 'moderated' => $moderated,
      'states' => array_values($states), 'defaults' => ['state' => $state,
        'promote' => (bool) $node->get('promote')->value, 'sticky' => (bool) $node->get('sticky')->value],
      'canDelete' => $uuid !== NULL && $node->access('delete', $this->account)];
  }

  /** Describes the comment body format without exposing comment content or configuration. */
  public function comment(string $bundle, ?string $uuid): array {
    if (!$this->entities->hasDefinition('comment_type') ||
      !$this->entities->getStorage('comment_type')->load($bundle)) {
      throw new NotFoundHttpException();
    }
    $resource = $this->resources?->get('comment', $bundle);
    if (!$resource || $resource->isInternal() || !$resource->isFieldEnabled('comment_body')) {
      throw new AccessDeniedHttpException();
    }
    $comment = $this->editableEntity('comment', $bundle, $uuid, ['uid' => $this->account->id()]);
    if (!$comment->hasField('comment_body') || !$this->writable($comment->get('comment_body'))) {
      throw new AccessDeniedHttpException();
    }
    return $this->textSettings($comment->get('comment_body')) +
      ['key' => $resource->getPublicName('comment_body')];
  }

  private function requireAccount(): void {
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
  }

  private function editableEntity(string $type, string $bundle, ?string $uuid, array $values): ContentEntityInterface {
    $storage = $this->entities->getStorage($type);
    if ($uuid !== NULL) {
      $matches = $storage->loadByProperties(['uuid' => $uuid]);
      $entity = reset($matches);
      if (!$entity || $entity->bundle() !== $bundle) {
        throw new NotFoundHttpException();
      }
      if (!$entity->access('update', $this->account) || !$entity->access('view', $this->account)) {
        throw new AccessDeniedHttpException();
      }
      return $entity;
    }
    if (!$this->entities->getAccessControlHandler($type)->createAccess($bundle, $this->account)) {
      throw new AccessDeniedHttpException();
    }
    return $storage->create([$this->entities->getDefinition($type)->getKey('bundle') => $bundle] + $values);
  }

  private function writable(FieldItemListInterface $items): bool {
    $entity = $items->getEntity();
    // Legacy node types can leave new_revision unset. Core passes that NULL to
    // allowedIf(), which requires a boolean, when non-admins edit revision_log.
    if ($items->getFieldDefinition()->getName() === 'revision_log' && $entity instanceof NodeInterface &&
      !$this->account->hasPermission('administer nodes') &&
      $entity->get('type')->entity->shouldCreateNewRevision() === NULL) {
      return FALSE;
    }
    return !$items->getFieldDefinition()->isReadOnly() &&
      $items->access('view', $this->account) && $items->access('edit', $this->account);
  }

  private function describeField(FieldItemListInterface $items, string $key): ?array {
    $definition = $items->getFieldDefinition();
    $type = $definition->getType();
    $multiple = $definition->getFieldStorageDefinition()->getCardinality() !== 1;
    $supported = in_array($type, ['string', 'string_long', 'boolean', 'integer', 'decimal', 'float',
      'text', 'text_long', 'text_with_summary', 'list_string', 'list_integer', 'list_float', 'entity_reference'], TRUE);
    $field = ['key' => $key, 'fieldType' => $type, 'label' => (string) $definition->getLabel(),
      'required' => $definition->isRequired(), 'multiple' => $multiple];
    if ($type === 'entity_reference') {
      $target = $definition->getSetting('target_type');
      $bundles = array_keys($definition->getSetting('handler_settings')['target_bundles'] ?? []);
      $supported = in_array($target, ['taxonomy_term', 'media'], TRUE) && count($bundles) === 1 &&
        ($target !== 'media' || !$multiple);
      if ($supported) {
        $target_resource = $this->resources->get($target, $bundles[0]);
        $supported = $target_resource && !$target_resource->isInternal();
        if ($supported) {
          $field += ['targetType' => $target, 'targetBundle' => $bundles[0],
            'targetResourceType' => $target_resource->getTypeName(),
            'autoCreate' => $target === 'taxonomy_term' &&
              !empty($definition->getSetting('handler_settings')['auto_create']) &&
              $this->entities->getAccessControlHandler($target)->createAccess($bundles[0], $this->account)];
        }
      }
    }
    elseif ($multiple) {
      $supported = FALSE;
    }
    if (!$supported) {
      if ($definition->isRequired() && $items->isEmpty()) {
        throw new UnprocessableEntityHttpException('unsupported_required_field');
      }
      return NULL;
    }
    if (in_array($type, ['text', 'text_long', 'text_with_summary'], TRUE)) {
      $field += $this->textSettings($items);
    }
    if ($type === 'string') {
      $field['maxLength'] = $definition->getSetting('max_length');
    }
    if (str_starts_with($type, 'list_')) {
      $field['options'] = [];
      foreach (options_allowed_values($definition->getFieldStorageDefinition(), $items->getEntity()) as $value => $label) {
        $field['options'][] = ['value' => $value, 'label' => (string) $label];
      }
    }
    return $field;
  }

  private function textSettings(FieldItemListInterface $items): array {
    $allowed = $items->getFieldDefinition()->getSetting('allowed_formats') ?? [];
    $formats = [];
    foreach ($this->formats->getFormatsForAccount($this->account) as $id => $format) {
      if ($allowed && !in_array($id, $allowed, TRUE)) {
        continue;
      }
      $filters = $format->get('filters');
      $formats[$id] = ['value' => $id, 'label' => (string) $format->label(),
        'editor' => $id === 'json' ? 'json' : (!empty($filters['filter_html_escape']['status']) ? 'plain_text' : 'rich_text')];
    }
    $original = $items->first()?->get('format')->getValue();
    // Keep the existing format. Replacing an inaccessible format can corrupt content.
    if (!$formats || ($original && !isset($formats[$original]))) {
      throw new AccessDeniedHttpException();
    }
    $selected = $original ?: array_key_first($formats);
    return ['textFormat' => $selected, 'editor' => $formats[$selected]['editor'],
      'jsonFormat' => $formats[$selected]['editor'] === 'json', 'formats' => array_values($formats)];
  }

}

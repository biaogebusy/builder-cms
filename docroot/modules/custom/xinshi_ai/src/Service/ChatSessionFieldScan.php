<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/** Read configuration only and verify the common existing-site field contract. */
final class ChatSessionFieldScan {

  public function __construct(
    private readonly EntityFieldManagerInterface $fields,
    private readonly EntityTypeManagerInterface $entities,
    private readonly ResourceTypeRepositoryInterface $resources,
  ) {}

  /** Return configuration fingerprints and differences, never entity content. */
  public function scan(int $version = 2): array {
    $expected = self::sourceDefinitions();
    $differences = $source = $targets = [];
    $target_definitions = ChatSessionFields::definitions($version);
    foreach ($target_definitions as $name => $definition) {
      $storage = $this->entities->getStorage('field_storage_config')->load('node.' . $name);
      $targets[$name] = ['storage_exists' => (bool) $storage, 'installed' => FALSE];
      if ($storage) {
        $bundles = array_values($storage->getBundles());
        sort($bundles, SORT_STRING);
        $targets[$name] += ['type' => $storage->getType(), 'bundles' => $bundles,
          'cardinality' => $storage->getCardinality(), 'revisionable' => $storage->isRevisionable(),
          'translatable' => $storage->isTranslatable(),
          'settings_sha256' => self::fingerprint($storage->getSettings())];
        $matching = $storage->getType() === $definition['type'] &&
          $storage->getCardinality() === ($definition['cardinality'] ?? 1) &&
          $storage->isRevisionable() && !$storage->isTranslatable() &&
          array_diff($bundles, ['ai_session']) === [];
        foreach ($definition['settings'] as $key => $value) {
          $matching = $matching && $storage->getSetting($key) === $value;
        }
        if (!$matching) {
          $differences[] = ['bundle' => 'ai_session', 'field' => $name, 'code' => 'target_storage_conflict'];
        }
      }
    }
    foreach ($expected as $bundle => $definitions) {
      $type = $this->entities->getStorage('node_type')->load($bundle);
      $resource = $this->resources->get('node', $bundle);
      $source[$bundle] = ['exists' => (bool) $type,
        'new_revision' => $type?->shouldCreateNewRevision(),
        'resource_available' => $resource && !$resource->isInternal(), 'fields' => []];
      if (!$type || !$resource) {
        $differences[] = ['bundle' => $bundle, 'field' => NULL, 'code' => 'bundle_or_resource_missing'];
        continue;
      }
      if (!$type->shouldCreateNewRevision()) {
        $differences[] = ['bundle' => $bundle, 'field' => NULL, 'code' => 'revision_policy'];
      }
      if ($resource->isInternal()) {
        $differences[] = ['bundle' => $bundle, 'field' => NULL, 'code' => 'resource_disabled'];
      }
      $actual = $this->fields->getFieldDefinitions('node', $bundle);
      foreach ($actual as $name => $field) {
        if ($field->isComputed()) {
          if (isset($definitions[$name]) || isset($target_definitions[$name])) {
            $differences[] = ['bundle' => $bundle, 'field' => $name, 'code' => 'computed_field'];
          }
          continue;
        }
        $storage = $field->getFieldStorageDefinition();
        $target = $bundle === 'ai_session' && isset($target_definitions[$name]);
        $bundles = $storage->isBaseField() ? [] : array_values($storage->getBundles());
        sort($bundles, SORT_STRING);
        $snapshot = ['type' => $field->getType(), 'cardinality' => $storage->getCardinality(),
          'required' => $field->isRequired(), 'translatable' => $field->isTranslatable(),
          'storage_translatable' => $storage->isTranslatable(), 'revisionable' => $storage->isRevisionable(),
          'base' => $storage->isBaseField(), 'public' => $resource->getPublicName($name),
          'enabled' => $resource->isFieldEnabled($name),
          'bundles' => $bundles, 'settings' => self::settings($field->getSettings()),
          'settings_sha256' => self::fingerprint($field->getSettings()),
          'defaults_sha256' => self::fingerprint([$field->getDefaultValueLiteral(),
            $field->getDefaultValueCallback()])];
        if ($target) {
          $targets[$name]['installed'] = TRUE;
          $targets[$name]['field'] = $snapshot;
          $definition = $target_definitions[$name];
          $definition += ['cardinality' => 1, 'required' => FALSE];
        }
        else {
          $source[$bundle]['fields'][$name] = $snapshot;
          $definition = $definitions[$name] ?? NULL;
        }
        if ($definition === NULL) {
          if (!$storage->isBaseField()) {
            $differences[] = ['bundle' => $bundle, 'field' => $name, 'code' => 'unexpected_field'];
          }
          continue;
        }
        $matching = $snapshot['type'] === $definition['type'] &&
          $snapshot['cardinality'] === ($definition['cardinality'] ?? 1) &&
          $snapshot['required'] === ($definition['required'] ?? FALSE) &&
          !$snapshot['translatable'] &&
          $snapshot['revisionable'] && !$snapshot['base'] &&
          $snapshot['public'] === $name && $snapshot['enabled'];
        foreach ($definition['settings'] ?? [] as $key => $value) {
          $matching = $matching && ($snapshot['settings'][$key] ?? NULL) === $value;
        }
        if ($name === 'sessions') {
          $matching = $matching && $snapshot['settings']['target_bundles'] === ['ai_session'];
        }
        if (in_array($name, ['content', 'reasoning_content', 'summary', 'total_tokens', 'sessions'], TRUE)) {
          $matching = $matching && $bundles === [$bundle];
        }
        if ($target) {
          $matching = $matching && !$snapshot['storage_translatable'] &&
            $field->getDefaultValueLiteral() === [] &&
            $field->getDefaultValueCallback() === '';
        }
        if (!$matching) {
          $differences[] = ['bundle' => $bundle, 'field' => $name, 'code' => 'definition_mismatch'];
        }
      }
      foreach (array_diff(array_keys($definitions), array_keys($actual)) as $name) {
        $differences[] = ['bundle' => $bundle, 'field' => $name, 'code' => 'field_missing'];
      }
    }
    if ($version === 3) {
      foreach (array_keys(ChatSessionFields::definitions()) as $name) {
        if (!$targets[$name]['installed']) {
          $differences[] = ['bundle' => 'ai_session', 'field' => $name, 'code' => 'field_missing'];
        }
      }
    }
    return ['version' => 1, 'operation' => 'configuration-only',
      'matches' => $differences === [], 'sourceFingerprint' => self::fingerprint($source),
      'targetFingerprint' => self::fingerprint($targets),
      'source' => $source, 'targets' => $targets, 'differences' => $differences];
  }

  /** Expose only bounded structural settings; retain other settings only as a hash. */
  private static function settings(array $settings): array {
    $result = [];
    foreach (['max_length', 'unsigned', 'case_sensitive'] as $name) {
      if (isset($settings[$name]) && (is_int($settings[$name]) || is_bool($settings[$name]))) {
        $result[$name] = $settings[$name];
      }
    }
    foreach (['size', 'target_type', 'handler'] as $name) {
      if (isset($settings[$name]) && is_string($settings[$name]) &&
        preg_match('/^[a-z0-9_:]{1,128}$/D', $settings[$name])) {
        $result[$name] = $settings[$name];
      }
    }
    $bundles = $settings['handler_settings']['target_bundles'] ?? [];
    $result['target_bundles'] = is_array($bundles) ? array_values($bundles) : [];
    foreach ($result['target_bundles'] as $name) {
      if (!is_string($name) || !preg_match('/^[a-z0-9_]{1,32}$/D', $name)) {
        $result['target_bundles'] = [];
        break;
      }
    }
    sort($result['target_bundles'], SORT_STRING);
    return $result;
  }

  /** The field structure shared by existing sites, confirmed against the first inventory. */
  public static function sourceDefinitions(): array {
    $string = ['type' => 'string', 'settings' => ['max_length' => 255]];
    return [
      'conversation' => [
        'ai_optimized' => ['type' => 'boolean'], 'chat_type' => $string,
        'max_tokens' => ['type' => 'integer', 'settings' => ['size' => 'normal']],
        'mode' => $string, 'model' => $string + ['required' => TRUE],
        'platform' => $string + ['required' => TRUE],
        'sessions' => ['type' => 'entity_reference', 'cardinality' => -1,
          'settings' => ['target_type' => 'node']],
        'temperature' => ['type' => 'float'], 'top_p' => ['type' => 'float'],
      ],
      'ai_session' => [
        'content' => ['type' => 'text_long'], 'reasoning_content' => ['type' => 'string_long'],
        'session_role' => $string + ['required' => TRUE], 'img' => $string,
        'style_preset' => $string, 'summary' => ['type' => 'string', 'settings' => ['max_length' => 2500]],
        'total_tokens' => ['type' => 'integer', 'settings' => ['size' => 'normal']],
        'uiparsing' => ['type' => 'boolean'],
      ],
    ];
  }

  private static function fingerprint(array $value): string {
    $sort = static function (mixed $item) use (&$sort): mixed {
      if (!is_array($item)) {
        return $item;
      }
      if (!array_is_list($item)) {
        ksort($item, SORT_STRING);
      }
      return array_map($sort, $item);
    };
    return hash('sha256', json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
  }

}

<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\StorageInterface;
use Drupal\xinshi_chat\Schema\Definition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Prevents a stale configuration export from undoing a verified structure update. */
final class ConfigImportSubscriber implements EventSubscriberInterface {

  /** Validate the source configuration before Drupal changes active configuration. */
  public function validate(ConfigImporterEvent $event): void {
    $importer = $event->getConfigImporter();
    $source = $importer->getStorageComparer()->getSourceStorage();
    foreach (self::errors($source) as $error) {
      $importer->logError($error);
    }
  }

  /** Also used during first-time installation from a full configuration import. */
  public static function errors(StorageInterface $source): array {
    if (!array_key_exists('xinshi_chat', $source->read('core.extension')['module'] ?? [])) {
      return [];
    }
    $errors = [];
    if (($source->read('xinshi_chat.settings')['schema_version'] ?? NULL) !== Definition::CURRENT ||
      ($source->read('xinshi_ai_reference.settings')['storage_version'] ?? NULL) !== Definition::STORAGE_VERSION) {
      $errors[] = 'Chat structure or storage version is not current.';
    }
    foreach (Definition::fields() as $bundle => $fields) {
      foreach ($source->listAll('field.field.node.' . $bundle . '.') as $name) {
        if (!array_key_exists($source->read($name)['field_name'], $fields)) {
          $errors[] = 'Unmanaged Chat field: ' . $name;
        }
      }
      $type = $source->read('node.type.' . $bundle);
      if (!$type || empty($type['new_revision']) ||
        !in_array('xinshi_chat', $type['dependencies']['enforced']['module'] ?? [], TRUE)) {
        $errors[] = 'Chat content type is missing its revision policy or module ownership: ' . $bundle;
      }
      foreach ($fields as $name => $expected) {
        $storage = $source->read('field.storage.node.' . $name);
        $field = $source->read('field.field.node.' . $bundle . '.' . $name);
        $valid = $storage && $field && $storage['type'] === $expected['type']
          && $storage['cardinality'] === $expected['cardinality']
          && $field['required'] === $expected['required']
          && $field['translatable'] === $expected['translatable'];
        foreach ($expected['settings'] as $key => $value) {
          $valid = $valid && ($storage['settings'][$key] ?? NULL) === $value;
        }
        foreach ($expected['field_settings'] as $key => $value) {
          $valid = $valid && ($key === 'handler_settings'
            ? array_values($field['settings'][$key]['target_bundles'] ?? []) === array_values($value['target_bundles'])
            : ($field['settings'][$key] ?? NULL) === $value);
        }
        if (array_key_exists('storage_translatable', $expected)) {
          $valid = $valid && ($storage['translatable'] ?? NULL) === $expected['storage_translatable'];
        }
        if (array_key_exists('default_value', $expected)) {
          $valid = $valid && ($field['default_value'] ?? NULL) === $expected['default_value']
            && ($field['default_value_callback'] ?? NULL) === $expected['default_value_callback'];
        }
        if (!$valid) {
          $errors[] = 'Chat field does not match the installed structure: ' . $bundle . '.' . $name;
        }
        if ($expected['exclusive']) {
          foreach ($source->listAll('field.field.node.') as $config_name) {
            $other = $source->read($config_name);
            if (($other['field_name'] ?? NULL) === $name && ($other['bundle'] ?? NULL) !== $bundle) {
              $errors[] = 'Chat storage cannot be shared: ' . $name;
            }
          }
        }
      }
      $resource = $source->read('jsonapi_extras.jsonapi_resource_config.node--' . $bundle);
      $default_disabled = $source->read('jsonapi_extras.settings')['default_disabled'] ?? FALSE;
      if (($resource && !empty($resource['disabled'])) || (!$resource && $default_disabled)) {
        $errors[] = 'Chat JSON:API resource cannot be disabled: ' . $bundle;
      }
      foreach ($resource['resourceFields'] ?? [] as $field) {
        $name = $field['fieldName'] ?? '';
        if (isset($fields[$name]) && (!empty($field['disabled']) ||
          ($field['publicName'] ?? $name) !== $name)) {
          $errors[] = 'Chat JSON:API fields must keep their public contract: ' . $bundle . '.' . $name;
        }
      }
    }
    return $errors;
  }

  /** {@inheritdoc} */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::IMPORT_VALIDATE => 'validate'];
  }

}

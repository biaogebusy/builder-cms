<?php

declare(strict_types=1);

namespace Drupal\xinshi_entity_tools\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/** Versioned opt-in resources; discovery never creates site configuration. */
#[ConfigEntityType(
  id: 'entity_tool_resource',
  label: new TranslatableMarkup('Entity tool resource'),
  handlers: [
    'list_builder' => 'Drupal\Core\Config\Entity\ConfigEntityListBuilder',
    'form' => [
      'add' => 'Drupal\xinshi_entity_tools\Form\ResourceForm',
      'edit' => 'Drupal\xinshi_entity_tools\Form\ResourceForm',
    ],
  ],
  config_prefix: 'resource',
  entity_keys: ['id' => 'id', 'label' => 'label', 'status' => 'status'],
  admin_permission: 'administer entity tool resources',
  links: ['collection' => '/admin/config/services/entity-tools',
    'add-form' => '/admin/config/services/entity-tools/add',
    'edit-form' => '/admin/config/services/entity-tools/{entity_tool_resource}'],
  config_export: ['id', 'label', 'version', 'bundle', 'fields', 'write_fields', 'languages',
    'text_format', 'allow_create', 'allow_update'],
)]
final class EntityToolResource extends ConfigEntityBase {

  protected $id;
  protected $label;
  protected $version = 1;
  protected $bundle;
  protected $fields = [];
  protected $write_fields = [];
  protected $languages = [];
  protected $text_format = 'plain_text';
  protected $allow_create = FALSE;
  protected $allow_update = FALSE;

  /** Semantic changes invalidate approvals through the explicit resource version. */
  public function preSave(EntityStorageInterface $storage) {
    \Drupal::service('xinshi_entity_tools.resources')->validate($this);
    if (!$this->isNew()) {
      $old = $storage->loadUnchanged($this->getOriginalId());
      $keys = ['status', 'label', 'bundle', 'fields', 'write_fields', 'languages', 'text_format', 'allow_create', 'allow_update'];
      $changed = $old && array_intersect_key($old->toArray(), array_flip($keys)) !=
        array_intersect_key($this->toArray(), array_flip($keys));
      if (!$old || $old->id() !== $this->id() || $this->version < $old->get('version') ||
        ($changed && $this->version === $old->get('version'))) {
        throw new \InvalidArgumentException('Resource changes require the same ID and a newer version.');
      }
    }
    parent::preSave($storage);
  }

  /** Field and language removal also retires the dependent resource configuration. */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $this->addDependency('config', 'node.type.' . $this->bundle);
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $this->bundle);
    foreach ($this->fields as $name) {
      if ($definitions[$name]->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }
      foreach ([$definitions[$name], $definitions[$name]->getFieldStorageDefinition()] as $definition) {
        $dependency = $definition->getConfigDependencyName();
        $this->addDependency($definition->getConfigDependencyKey(), $dependency);
      }
    }
    foreach ($this->languages as $language) {
      if (\Drupal::entityTypeManager()->hasDefinition('configurable_language') &&
        \Drupal::entityTypeManager()->getStorage('configurable_language')->load($language)) {
        $this->addDependency('config', 'language.entity.' . $language);
      }
    }
    if ($this->text_format) {
      $this->addDependency('config', 'filter.format.' . $this->text_format);
    }
    return $this;
  }

}

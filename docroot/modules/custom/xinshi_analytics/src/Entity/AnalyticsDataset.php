<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/** Exportable mappings are opt-in and never created from discovered fields. */
#[ConfigEntityType(
  id: 'analytics_dataset',
  label: new TranslatableMarkup('Analytics dataset'),
  config_prefix: 'dataset',
  entity_keys: ['id' => 'id', 'label' => 'label', 'status' => 'status'],
  admin_permission: 'administer analytics datasets',
  config_export: ['id', 'label', 'version', 'entity_type', 'bundles', 'time_field',
    'dimensions', 'filters', 'query_policy'],
)]
final class AnalyticsDataset extends ConfigEntityBase {

  /** The public alias is immutable so saved requests cannot change targets. */
  protected $id;
  /** Human-readable dataset meaning, maintained by configuration administrators. */
  protected $label;
  /** Explicit semantic version; mapping changes require an increase. */
  protected $version;
  /** A supported SQL content entity type, never an arbitrary query adapter name. */
  protected $entity_type;
  /** Explicit allowed bundles; no wildcard automatically exposes new types. */
  protected $bundles = [];
  /** A single-valued timestamp field defining the half-open query interval. */
  protected $time_field;
  /** Public grouping aliases mapped to single-valued fields. */
  protected $dimensions = [];
  /** Public enum filter aliases mapped to single-valued fields. */
  protected $filters = [];
  /** Explicit language, calendar, scan and response limits; no site defaults. */
  protected $query_policy = [];

  /** Returns the independent dataset grant, without granting entity access. */
  public function queryPermission(): string {
    return 'query analytics dataset ' . $this->id();
  }

  /** Excludes lifecycle flags from semantic version comparison. */
  public function definition(): array {
    return array_intersect_key($this->toArray(), array_flip(['label', 'entity_type',
      'bundles', 'time_field', 'dimensions', 'filters', 'query_policy']));
  }

  /** {@inheritdoc} */
  public function preSave(EntityStorageInterface $storage) {
    \Drupal::service('xinshi_analytics.datasets')->validate($this);
    if (!$this->isNew()) {
      $original = $storage->loadUnchanged($this->getOriginalId());
      if (!$original || $original->id() !== $this->id()
        || $this->version < $original->get('version')
        || ($this->definition() != $original->definition()
          && $this->version === $original->get('version'))) {
        throw new \InvalidArgumentException('Dataset changes require the same ID and a newer version.');
      }
    }
    parent::preSave($storage);
    if (str_starts_with((string) $this->id(), 'node__')) {
      throw new \Drupal\xinshi_analytics\AnalyticsException('invalid_query');
    }
  }

  /** {@inheritdoc} */
  public function calculateDependencies() {
    parent::calculateDependencies();
    foreach (\Drupal::service('xinshi_analytics.datasets')->dependencies($this) as $type => $names) {
      foreach ($names as $name) {
        $this->addDependency($type, $name);
      }
    }
    return $this;
  }

}


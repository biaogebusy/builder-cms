<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\xinshi_analytics\Entity\AnalyticsDataset;

/** Builds ephemeral content-type mappings; discovery never creates configuration entities. */
final class NodeCountDatasets {

  /** A project opts in once with an explicit policy shared by its content types. */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly ConfigFactoryInterface $config,
  ) {}

  /** Uses reserved public IDs; configured aliases cannot shadow automatic types. */
  public function load(AccountInterface $account, ?string $id = NULL): array {
    $settings = $this->config->get('xinshi_analytics.node_counts');
    if ($settings->get('enabled') !== TRUE || !$account->hasPermission('access content')
      || !$this->entityTypes->hasDefinition('node_type')) {
      return [];
    }
    if ($id !== NULL && !preg_match('/^node__[a-z0-9_]{1,32}$/D', $id)) {
      return [];
    }
    $storage = $this->entityTypes->getStorage('node_type');
    $storage->resetCache();
    $types = $storage->loadMultiple($id === NULL ? NULL : [substr($id, 6)]);
    $datasets = [];
    foreach ($types as $type) {
      $definition = ['id' => 'node__' . $type->id(), 'label' => $type->label(),
        'status' => TRUE, 'entity_type' => 'node', 'bundles' => [$type->id()],
        'time_field' => 'created', 'dimensions' => [], 'filters' => [],
        'query_policy' => $settings->get('query_policy')];
      // A safe integer fingerprint changes with the type identity, label or shared policy.
      // Evidence epochs separately prevent old receipts reviving after a configuration revert.
      $definition['version'] = max(1, (int) hexdec(substr(hash('sha256',
        json_encode([$type->toArray(), $definition], JSON_THROW_ON_ERROR)), 0, 13)));
      $datasets[$definition['id']] = new AnalyticsDataset($definition, 'analytics_dataset');
    }
    return $datasets;
  }

}

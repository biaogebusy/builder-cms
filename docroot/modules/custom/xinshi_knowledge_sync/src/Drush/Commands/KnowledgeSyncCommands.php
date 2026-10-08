<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\xinshi_knowledge_sync\Service\SnapshotImporter;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Imports local snapshots through an explicitly selected administrative account. */
final class KnowledgeSyncCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'xinshi_knowledge_sync.importer')]
    private readonly SnapshotImporter $importer,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
  ) {
    parent::__construct();
  }

  /** Preview or apply one complete snapshot; run Search API indexing after applying. */
  #[CLI\Command(name: 'xinshi-knowledge:sync')]
  #[CLI\Argument(name: 'snapshot', description: 'Local JSON snapshot path (not a URL).')]
  #[CLI\Option(name: 'source', description: 'Expected administrator configured source id.')]
  #[CLI\Option(name: 'account', description: 'Active administrative Drupal user ID for this import.')]
  #[CLI\Option(name: 'apply', description: 'Apply the preview, including unpublishing missing source documents.')]
  #[CLI\Option(name: 'allow-empty', description: 'Accept a verified empty snapshot and unpublish this source language.')]
  public function sync(string $snapshot, array $options = ['source' => NULL, 'account' => NULL, 'apply' => FALSE, 'allow-empty' => FALSE]): int {
    if (!is_string($options['source']) || !is_string($options['account']) ||
        !ctype_digit($options['account']) || str_contains($snapshot, '://') ||
        !is_file($snapshot) || !is_readable($snapshot) || filesize($snapshot) > 20 * 1024 * 1024) {
      throw new \InvalidArgumentException('A local snapshot, --source and --account are required.');
    }
    $account = $this->entities->getStorage('user')->load($options['account']);
    if (!$account) {
      throw new \InvalidArgumentException('Unknown import account.');
    }
    $manifest = json_decode(file_get_contents($snapshot), TRUE, 32, JSON_THROW_ON_ERROR);
    $stats = $this->importer->import($manifest, $options['source'], $account, (bool) $options['apply'], (bool) $options['allow-empty']);
    $this->io()->definitionList(...array_map(fn($key, $value) => [$key => $value], array_keys($stats), $stats));
    return self::EXIT_SUCCESS;
  }

}

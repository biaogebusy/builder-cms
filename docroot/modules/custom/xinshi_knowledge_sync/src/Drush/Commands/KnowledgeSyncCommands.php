<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\xinshi_knowledge_sync\Service\SnapshotImporter;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

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
  #[CLI\Argument(name: 'snapshot', description: 'Local JSON snapshot path, relative to the directory where Drush was invoked.')]
  #[CLI\Option(name: 'source', description: 'Expected administrator configured source id.')]
  #[CLI\Option(name: 'account', description: 'Active administrative Drupal user ID for this import.')]
  #[CLI\Option(name: 'apply', description: 'Apply the preview, including unpublishing missing source documents.')]
  #[CLI\Option(name: 'allow-empty', description: 'Accept a verified empty snapshot and unpublish this source language.')]
  public function sync(string $snapshot, array $options = ['source' => NULL, 'account' => NULL, 'apply' => FALSE, 'allow-empty' => FALSE]): int {
    if (!is_string($options['source']) || $options['source'] === '') {
      throw new \InvalidArgumentException('The --source option is required.');
    }
    if (!is_string($options['account']) || !ctype_digit($options['account'])) {
      throw new \InvalidArgumentException('The --account option must be a numeric Drupal user ID.');
    }
    if (str_contains($snapshot, '://')) {
      throw new \InvalidArgumentException('The snapshot must be a local JSON file, not a URL.');
    }
    // Drupal bootstrap changes cwd; preserve the meaning of the caller's relative path.
    $snapshot = Path::makeAbsolute($snapshot, $this->getConfig()->cwd());
    if (!is_file($snapshot)) {
      throw new \InvalidArgumentException('Snapshot file not found or not a regular file: ' . $snapshot);
    }
    if (!is_readable($snapshot)) {
      throw new \InvalidArgumentException('Snapshot file is not readable: ' . $snapshot);
    }
    if (filesize($snapshot) > 20 * 1024 * 1024) {
      throw new \InvalidArgumentException('Snapshot file exceeds the 20 MiB limit: ' . $snapshot);
    }
    $account = $this->entities->getStorage('user')->load($options['account']);
    if (!$account) {
      throw new \InvalidArgumentException('Unknown import account.');
    }
    try {
      $manifest = json_decode(file_get_contents($snapshot), TRUE, 32, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $error) {
      throw new \InvalidArgumentException('Snapshot file is not valid JSON: ' . $snapshot .
        ' (' . $error->getMessage() . '). Re-export and copy the generated JSON file.', 0, $error);
    }
    if (!is_array($manifest)) {
      throw new \InvalidArgumentException('Snapshot file must contain a JSON object: ' . $snapshot);
    }
    $stats = $this->importer->import($manifest, $options['source'], $account, (bool) $options['apply'], (bool) $options['allow-empty']);
    $this->io()->definitionList(...array_map(fn($key, $value) => [$key => $value], array_keys($stats), $stats));
    return self::EXIT_SUCCESS;
  }

}

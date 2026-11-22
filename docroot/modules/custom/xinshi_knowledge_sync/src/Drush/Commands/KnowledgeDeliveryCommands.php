<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\xinshi_knowledge_sync\Service\DeliveryExecution;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

/** Provides machine-readable receipts to an operator-controlled delivery adapter. */
final class KnowledgeDeliveryCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'xinshi_knowledge_sync.delivery')]
    private readonly DeliveryExecution $delivery,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
  ) {
    parent::__construct();
  }

  /** Executes one immutable request; preview is an explicit request action. */
  #[CLI\Command(name: 'xinshi-knowledge:delivery')]
  #[CLI\Argument(name: 'request', description: 'Local delivery request JSON file.')]
  #[CLI\Argument(name: 'snapshot', description: 'Local original snapshot JSON file.')]
  #[CLI\Option(name: 'account', description: 'Active administrative Drupal user ID.')]
  public function execute(string $request, string $snapshot, array $options = ['account' => NULL]): int {
    $account = $this->account($options['account']);
    $input = json_decode($this->local($request, 4096), TRUE, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
      throw new \InvalidArgumentException('invalid_delivery_request');
    }
    $receipt = $this->delivery->execute($input, $this->local($snapshot, 20 * 1024 * 1024), $account);
    $this->output()->writeln(json_encode(['receipt' => $receipt], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    // Failed and running results remain queryable protocol results, never SSH success claims.
    return self::EXIT_SUCCESS;
  }

  /** Reads an original task without importing or indexing. */
  #[CLI\Command(name: 'xinshi-knowledge:delivery-receipt')]
  #[CLI\Argument(name: 'id', description: 'Original delivery task UUID.')]
  #[CLI\Option(name: 'account', description: 'Original active administrative Drupal user ID.')]
  public function receipt(string $id, array $options = ['account' => NULL]): int {
    $this->output()->writeln(json_encode(['receipt' => $this->delivery->receipt($id, $this->account($options['account']))], JSON_THROW_ON_ERROR));
    return self::EXIT_SUCCESS;
  }

  private function account(mixed $uid): AccountInterface {
    if (!is_string($uid) || !preg_match('/^[1-9][0-9]{0,9}$/D', $uid) ||
        !($account = $this->entities->getStorage('user')->load($uid))) {
      throw new \InvalidArgumentException('invalid_delivery_account');
    }
    return $account;
  }

  private function local(string $name, int $maximum): string {
    $path = Path::makeAbsolute($name, $this->getConfig()->cwd());
    if (str_contains($name, '://') || is_link($path) || !is_file($path) || !is_readable($path) || filesize($path) > $maximum) {
      throw new \InvalidArgumentException('invalid_delivery_file');
    }
    $bytes = file_get_contents($path);
    if ($bytes === FALSE || strlen($bytes) > $maximum || !mb_check_encoding($bytes, 'UTF-8')) {
      throw new \InvalidArgumentException('invalid_delivery_file');
    }
    return $bytes;
  }

}

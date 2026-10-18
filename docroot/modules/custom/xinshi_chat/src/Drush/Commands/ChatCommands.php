<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Drush\Commands;

use Drupal\xinshi_chat\Schema\Manager;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Deployment checks never silently repair or migrate a running site. */
final class ChatCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'xinshi_chat.schema')]
    private readonly Manager $schema,
  ) {
    parent::__construct();
  }

  /** Check the complete managed structure after installation, updates and config import. */
  #[CLI\Command(name: 'xinshi-chat:check')]
  public function check(): int {
    $report = $this->schema->inspect();
    $this->output()->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    return $report['matches'] && $report['installed_version'] === $report['target_version']
      ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
  }

  /** Explicitly resume initialization or restore missing compatible configuration. */
  #[CLI\Command(name: 'xinshi-chat:sync')]
  public function sync(): int {
    $this->schema->apply();
    return $this->check();
  }

}

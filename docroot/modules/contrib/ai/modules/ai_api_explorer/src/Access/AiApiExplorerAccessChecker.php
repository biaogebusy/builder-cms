<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai_api_explorer\AiApiExplorerPluginManager;

/**
 * Checks if passed parameter matches the route configuration.
 */
final class AiApiExplorerAccessChecker implements AccessInterface {

  public function __construct(private readonly AiApiExplorerPluginManager $pluginManager) {
  }

  /**
   * Checks the access to Ai API Explorer Forms.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The logged-in user.
   * @param string|null $plugin_id
   *   The plugin being checked.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function access(AccountInterface $account, ?string $plugin_id = NULL): AccessResult {
    $definition = $this->pluginManager->getDefinition($plugin_id);

    /** @var \Drupal\ai_api_explorer\AiApiExplorerInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, $definition);

    if (!$plugin) {
      return AccessResult::forbidden('Plugin does not exist.');
    }

    return AccessResult::allowedIf($plugin->isActive() && $plugin->hasAccess($account));
  }

}

<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\Routing\Route;

/**
 * Checks if access to advanced AI Automator features should be granted.
 */
final class AutomatorAdvancedAccessChecker implements AccessInterface {

  /**
   * Constructs an AutomatorAdvancedAccessChecker object.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Access callback.
   */
  public function access(Route $route, AccountInterface $account): AccessResult {
    if ($this->moduleHandler->moduleExists('ai_ckeditor') || $this->moduleHandler->moduleExists('ai_agents') || Settings::get('ai_automator_advanced_mode_enabled', FALSE)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}

<?php

declare(strict_types=1);

namespace Drupal\private_message\Controller;

/**
 * Handles page callbacks for the Private Message module.
 */
interface PrivateMessageControllerInterface {

  /**
   * The Private message page.
   *
   * @return array
   *   Render array.
   */
  public function privateMessagePage(): array;

  /**
   * The private message module settings page.
   *
   * @return array
   *   Render array.
   */
  public function pmSettingsPage(): array;

  /**
   * The settings page specific to private message threads.
   *
   * @return array
   *   Render array.
   */
  public function pmThreadSettingsPage(): array;

  /**
   * Provides a controller for the private_message.admin_config.config route.
   *
   * @return array
   *   Render array.
   */
  public function configPage(): array;

  /**
   * The page for preparing to uninstall the module.
   *
   * @return array
   *   Render array.
   */
  public function adminUninstallPage(): array;

  /**
   * The page for banning and unbanning users.
   *
   * @return array
   *   Render array.
   */
  public function banUnbanPage(): array;

}

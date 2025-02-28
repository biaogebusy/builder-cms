<?php

namespace Drupal\private_message\Controller;

/**
 * Handles page callbacks for the Private Message module.
 */
interface PrivateMessageControllerInterface {

  /**
   * The Private message page.
   */
  public function privateMessagePage();

  /**
   * The private message module settings page.
   */
  public function pmSettingsPage();

  /**
   * The settings page specific to private message threads.
   */
  public function pmThreadSettingsPage();

  /**
   * The page for preparing to uninstall the module.
   */
  public function adminUninstallPage();

}

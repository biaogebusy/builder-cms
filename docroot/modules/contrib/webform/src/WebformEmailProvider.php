<?php

namespace Drupal\webform;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Manages and provides HTML email support.
 */
class WebformEmailProvider implements WebformEmailProviderInterface {

  /**
   * Constructs a WebformEmailProvider object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration object factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler class to use for loading includes.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   Mail manager service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   Module extension list service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
    protected MailManagerInterface $mailManager,
    protected ModuleExtensionList $moduleExtensionList,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getModules() {
    return [
      // Mail System - https://www.drupal.org/project/mailsystem
      'mailsystem',
      // SMTP Authentication Support - https://www.drupal.org/project/smtp
      'smtp',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function check() {
    // Don't override the system.mail.interface.webform if the default interface
    // is the 'test_mail_collector'.
    if ($this->configFactory->get('system.mail')->get('interface.default') === 'test_mail_collector') {
      return $this->uninstall();
    }

    // Check if a contrib module is handling sending email.
    $mail_modules = $this->getModules();
    foreach ($mail_modules as $module) {
      if ($this->moduleEnabled($module)) {
        return $this->uninstall();
      }
    }

    // Finally, check if the default mail interface and see if it still uses the
    // php_mail. This check allow unknown contrib modules to handle sending
    // HTML emails.
    if ($this->configFactory->get('system.mail')->get('interface.default') === 'php_mail') {
      return $this->install();
    }
    else {
      return $this->uninstall();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function installed() {
    return ($this->configFactory->get('system.mail')->get('interface.webform') === 'webform_php_mail');
  }

  /**
   * {@inheritdoc}
   */
  public function install() {
    $config = $this->configFactory->getEditable('system.mail');
    $mail_plugins = $config->get('interface');
    if (!isset($mail_plugins['webform']) || $mail_plugins['webform'] !== 'webform_php_mail') {
      $mail_plugins['webform'] = 'webform_php_mail';
      $config->set('interface', $mail_plugins)->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall() {
    $config = $this->configFactory->getEditable('system.mail');
    $mail_plugins = $config->get('interface');
    if (isset($mail_plugins['webform'])) {
      unset($mail_plugins['webform']);
      $config->set('interface', $mail_plugins)->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getModule() {
    if ($this->installed()) {
      return 'webform';
    }
    else {
      $modules = $this->getModules();
      foreach ($modules as $module) {
        if ($this->moduleEnabled($module)) {
          return $module;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getModuleName() {
    return ($module = $this->getModule()) ? $this->moduleExtensionList->getName($module) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function moduleEnabled($module) {
    // Make sure module exists and is installed.
    if (!$this->moduleHandler->moduleExists($module)) {
      return FALSE;
    }

    // Make sure SMTP module is enabled.
    if ($module === 'smtp' && !$this->configFactory->get('smtp.settings')->get('smtp_on')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMailPluginId() {
    $config = $this->configFactory->get('system.mail');
    return $config->get('interface.webform') ?: $config->get('interface.default') ?: FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getMailPluginDefinition() {
    $plugin_id = $this->getMailPluginId();
    return ($plugin_id && $this->mailManager->hasDefinition($plugin_id)) ? $this->mailManager->getDefinition($plugin_id) : NULL;
  }

}

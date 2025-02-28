<?php

namespace Drupal\lightning_core\Update;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\lightning_core\ConfigHelper as Config;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains optional configuration updates targeting Lightning Core 3.6.0.
 *
 * @Update("3.6.0")
 */
final class Update360 implements ContainerInjectionInterface {

  /**
   * The module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  private $moduleInstaller;

  /**
   * Update360 constructor.
   *
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer service.
   */
  public function __construct(ModuleInstallerInterface $module_installer) {
    $this->moduleInstaller = $module_installer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('module_installer'));
  }

  /**
   * Enables avatars for user accounts.
   *
   * @update
   *
   * @ask Do you want to enable pictures for user accounts?
   */
  public function enableUserPictures() {
    $this->moduleInstaller->install(['image']);

    $config = Config::forModule('lightning_core')->optional();
    $config->getEntity('field_storage_config', 'user.user_picture')->save();
    $config->getEntity('field_config', 'user.user.user_picture')->save();
    $config->getEntity('entity_view_display', 'user.user.compact')->save();
  }

}

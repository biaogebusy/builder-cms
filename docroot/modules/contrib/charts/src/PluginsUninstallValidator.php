<?php

namespace Drupal\charts;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Prevents uninstalling of a module providing default chart library plugin.
 */
class PluginsUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new ChartsPluginsUninstallValidator.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];
    $config = $this->configFactory->get('charts.settings');
    $dependent_modules = $config->get('dependencies.module') ?? [];
    if (in_array($module, $dependent_modules)) {
      $reasons[] = $this->t('Provides a chart library or chart type plugin configured as the default option for chart. Please update <a href="/admin/config/content/charts?destination=/admin/modules/uninstall">the configuration</a> or reset them before uninstalling it.');
    }
    return $reasons;
  }

}

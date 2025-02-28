<?php

namespace Drupal\image_effects;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Prevents uninstalling modules that Image Effects configuration require.
 */
class ImageEffectsUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new ImageEffectsUninstallValidator.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TranslationInterface $string_translation) {
    $this->configFactory = $config_factory;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];
    // Prevents uninstalling 'jquery_colorpicker' if its color selector plugin
    // is in use.
    if ($module == 'jquery_colorpicker' && $this->configFactory->get('image_effects.settings')->get('color_selector.plugin_id') === 'jquery_colorpicker') {
      $reasons[] = $this->t('The <em>Image Effects</em> module is using the <em>JQuery Colorpicker</em> color selector.');
    }
    // Prevents uninstalling 'color' if the farbtastic color selector plugin
    // is in use.
    // @todo remove versioning once Drupal 9 is no longer  supported.
    if ((version_compare(\Drupal::VERSION, 10) >= 0) && $module == 'color' && $this->configFactory->get('image_effects.settings')->get('color_selector.plugin_id') === 'farbtastic') {
      $reasons[] = $this->t('The <em>Image Effects</em> module is using the <em>Farbtastic</em> color selector.');
    }
    return $reasons;
  }

}

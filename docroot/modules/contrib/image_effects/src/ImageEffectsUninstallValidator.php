<?php

declare(strict_types=1);

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

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];
    // Prevents uninstalling 'color' if the farbtastic color selector plugin
    // is in use.
    if ($module == 'color' && $this->configFactory->get('image_effects.settings')->get('color_selector.plugin_id') === 'farbtastic') {
      $reasons[] = $this->t('The <em>Image Effects</em> module is using the <em>Farbtastic</em> color selector.');
    }
    return $reasons;
  }

}

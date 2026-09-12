<?php

declare(strict_types=1);

namespace Drupal\diff\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Requirements hooks for Diff module.
 */
class DiffRequirementsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $requirements = [];
    $config = $this->configFactory->get('diff.settings');
    $setting_enabled = $config->get('general_settings.layout_plugins.visual_inline.enabled') === TRUE;
    $has_htmlDiffAdvanced = \class_exists('\HtmlDiffAdvanced');

    $requirements['html_diff_advanced'] = [
      'title' => $this->t('Diff'),
      'value' => $this->t('Installed correctly'),
      'description' => $this->t('Diff module has been installed correctly.'),
    ];
    if (!$has_htmlDiffAdvanced) {
      if ($setting_enabled) {
        $requirements['html_diff_advanced']['value'] = $this->t('Dependencies not found');
        $requirements['html_diff_advanced']['severity'] = RequirementSeverity::Error;
        $requirements['html_diff_advanced']['description'] = $this->t("The HTML Diff layout requires the HtmlDiffAdvanced library. Please consult README.txt for installation instructions.");
      }
      else {
        $requirements['html_diff_advanced']['value'] = $this->t('Visual inline layout');
        $requirements['html_diff_advanced']['severity'] = RequirementSeverity::Info;
        $requirements['html_diff_advanced']['description'] = $this->t('Diff adds a visual rendered display, consult README.txt for installation instructions and enable it in <a href=":settings">settings</a>.', [':settings' => Url::fromRoute('diff.general_settings')->toString()]);
      }
    }

    return $requirements;
  }

}

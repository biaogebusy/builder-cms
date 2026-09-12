<?php

declare(strict_types=1);

namespace Drupal\diff\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Help hooks for Diff module.
 */
class DiffHelpHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string {
    switch ($route_name) {
      case 'help.page.diff':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('The Diff module replaces the normal <em>Revisions </em> node tab and enhances the listing of revisions with an option to view the differences between any two content revisions.') . '</p>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Compare content entity revisions') . '</dt>';
        $output .= '<dd>' . $this->t('Diff provides the possibility of comparing two node revisions but it also provides support for comparing any two content entities. With minimum effort it can be extended to display differences between any two content entities.') . '</dd>';
        $output .= '<dt>' . $this->t('Control field visibility settings') . '</dt>';
        $output .= '<dd>' . $this->t('Fields visibility can be controlled from view modes for configurable fields and from Diff settings page for entity base fields. Diff field types specific settings can also be configured from Diff settings page') . '</dd>';
        $output .= '<dt>' . $this->t('Configure diff field type settings') . '</dt>';
        $output .= '<dd>' . $this->t('Every field type has specific diff settings (display or not the field title, markdown format or other settings). These settings can be configured from Diff settings page') . '</dd>';
        $output .= '</dl>';
        return $output;

      case 'diff.general_settings':
        return '<p>' . $this->t('Configurations for the revision comparison functionality and diff layout plugins.') . '</p>';

      case 'diff.revision_overview':
        return '<p>' . $this->t('Revisions allow you to track differences between multiple versions of your content, and revert to older versions.') . '</p>';

      case 'diff.fields_list':
        return '<p>' . $this->t('This table provides a summary of the field support found on the system. For every field, a diff plugin can be selected and configured. These settings are applied to Unified and Split fields layouts.') . '</p>';
    }
    return '';
  }

}

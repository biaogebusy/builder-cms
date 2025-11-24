<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\image_effects\FontSelector;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\image_effects\Plugin\Attribute\FontSelector;

/**
 * Dropdown font selector plugin.
 *
 * Provides access to a list of fonts stored in a directory, specified in
 * configuration.
 */
#[FontSelector(
  id: "dropdown",
  title: new TranslatableMarkup("Dropdown font selector"),
  shortTitle: new TranslatableMarkup("Dropdown"),
  help: new TranslatableMarkup("Access a list of fonts stored in the directory specified in configuration."),
)]
class Dropdown extends Basic {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['path' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, array $ajax_settings = []) {
    $element['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#default_value' => $this->configuration['path'],
      '#maxlength' => 255,
      '#element_validate' => [[$this, 'validatePath']],
      '#description' => $this->t('Location of the directory where the fonts are stored.') . ' ' . $this->t('Relative paths will be resolved relative to the Drupal installation directory.'),
    ];
    return $element;
  }

  /**
   * Validation handler for the 'path' element.
   */
  public function validatePath(array $element, FormStateInterface $form_state, array $form): void {
    if (!is_dir($element['#value'])) {
      $form_state->setErrorByName(implode('][', $element['#parents']), $this->t('Invalid directory specified.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []): array {
    // Get list of font names.
    $fonts_list = $this->getList();
    if (empty($fonts_list)) {
      $this->logger->warning(
        'No fonts available. Make sure at least one font is available in the directory specified in the <a href=":url">configuration page</a>.',
        [':url' => Url::fromRoute('image_effects.settings')->toString()]
      );
      return [];
    }

    // Strip the path from the URI.
    $options['#default_value'] = isset($options['#default_value']) ? pathinfo($options['#default_value'], PATHINFO_BASENAME) : '';

    // Element.
    return array_merge([
      '#type' => 'select',
      '#title' => $this->t('Font'),
      '#description' => $this->t('Select a font.'),
      '#options' => $fonts_list,
      '#limit_validation_errors' => FALSE,
      '#required' => TRUE,
      '#element_validate' => [[$this, 'validateSelectorUri']],
    ], $options);
  }

  /**
   * Validation handler for the selection element.
   */
  public function validateSelectorUri(array $element, FormStateInterface $form_state, array $form): void {
    if (!empty($element['#value'])) {
      if (file_exists($file_path = $this->configuration['path'] . '/' . $element['#value'])) {
        $form_state->setValueForElement($element, $file_path);
      }
      else {
        $form_state->setErrorByName(implode('][', $element['#parents']), $this->t('The selected file does not exist.'));
      }
    }
  }

  /**
   * Return an array of fonts.
   *
   * Scans through files available in the directory specified through
   * configuration.
   *
   * @return string[]
   *   Array of font names.
   */
  protected function getList(): array {
    $filelist = [];
    if (is_dir($this->configuration['path']) && $handle = opendir($this->configuration['path'])) {
      while ($file_name = readdir($handle)) {
        if (preg_match("/\.[ot]tf$/i", $file_name) == 1) {
          $font_name = $this->getDescription($this->configuration['path'] . '/' . $file_name);
          if ($font_name !== NULL) {
            $filelist[$file_name] = $font_name;
          }
          else {
            $filelist[$file_name] = trim(substr($file_name, 0, -4));
          }
        }
      }
      closedir($handle);
    }
    asort($filelist);
    return $filelist;
  }

}

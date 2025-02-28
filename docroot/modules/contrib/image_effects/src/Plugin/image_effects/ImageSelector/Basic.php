<?php

namespace Drupal\image_effects\Plugin\image_effects\ImageSelector;

use Drupal\Core\Form\FormStateInterface;
use Drupal\image_effects\Plugin\ImageEffectsPluginBase;

/**
 * Basic image selector plugin.
 *
 * Allows typing in the image file URI/path.
 *
 * @Plugin(
 *   id = "basic",
 *   title = @Translation("Basic image selector"),
 *   short_title = @Translation("Basic"),
 *   help = @Translation("Allows typing in the image file URI/path.")
 * )
 */
class Basic extends ImageEffectsPluginBase {

  /**
   * {@inheritdoc}
   */
  public function selectionElement(array $options = []) {
    // Element.
    return array_merge([
      '#type' => 'textfield',
      '#title' => $this->t('Image URI/path'),
      '#description' => $this->t('An URI, an absolute path, or a relative path. Relative paths will be resolved relative to the Drupal installation directory.'),
      '#element_validate' => [[$this, 'validateSelectorUri']],
    ], $options);
  }

  /**
   * Validation handler for the selection element.
   */
  public function validateSelectorUri($element, FormStateInterface $form_state, $form) {
    if (!empty($element['#value'])) {
      if (!file_exists($element['#value'])) {
        $form_state->setErrorByName(implode('][', $element['#parents']), $this->t('The file does not exist.'));
      }
    }
  }

}

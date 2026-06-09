<?php

namespace Drupal\ai_translate;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configurable extractor plugin interface.
 *
 * Plugin configuration is normally stored in the third party settings
 * of a field configuration entity.
 */
interface ConfigurableFieldTextExtractorInterface extends FieldTextExtractorInterface {

  /**
   * Plugin settings for plugin.
   *
   * @param \Drupal\Core\Field\FieldConfigInterface $entity
   *   Field configuration entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $completeForm
   *   Complete form.
   */
  public function fieldSettingsForm(FieldConfigInterface $entity, FormStateInterface $form_state, array &$completeForm = []);

  /**
   * Submit callback for the settings form.
   *
   * @param \Drupal\Core\Field\FieldConfigInterface $entity
   *   Field configuration entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $completeForm
   *   Complete form.
   */
  public function submitFieldSettingForm(FieldConfigInterface $entity, FormStateInterface $form_state, array &$completeForm = []);

}

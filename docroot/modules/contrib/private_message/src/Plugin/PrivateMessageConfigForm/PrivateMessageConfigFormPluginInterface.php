<?php

namespace Drupal\private_message\Plugin\PrivateMessageConfigForm;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for PrivateMessageConfigForm plugins.
 */
interface PrivateMessageConfigFormPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Return the name of the crm tester plugin.
   *
   * @return string
   *   The name of the plugin.
   */
  public function getName();

  /**
   * Return the id of the crm tester plugin.
   *
   * @return string
   *   The id of the plugin.
   */
  public function getId();

  /**
   * Build the section of the form as it will appear on the settings page.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The Drupal form state.
   *
   * @return array
   *   A render array containing the form elements this plugin provides.
   */
  public function buildForm(FormStateInterface $formState);

  /**
   * Validate this section of the form.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The Drupal form state.
   *
   * @return array
   *   A render array containing the form elements this plugin provides.
   */
  public function validateForm(array &$form, FormStateInterface $formState);

  /**
   * Handle submission of the form added to the settings page.
   *
   * @param array $values
   *   An array of values for form elements added by this plugin.
   */
  public function submitForm(array $values);

}

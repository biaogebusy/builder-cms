<?php

declare(strict_types=1);

namespace Drupal\private_message\Plugin\PrivateMessageConfigForm;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface for PrivateMessageConfigForm plugins.
 */
interface PrivateMessageConfigFormPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Return the name of the crm tester plugin.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The name of the plugin.
   *
   * @deprecated in private_message:4.0.0 and is removed from
   *   private_message:5.0.0. Instead, you should just use the plugin
   *   ::getPluginId() method.
   *
   * @see https://www.drupal.org/node/3501696
   */
  public function getName(): MarkupInterface|string;

  /**
   * Return the id of the crm tester plugin.
   *
   * @return string
   *   The id of the plugin.
   *
   * @deprecated in private_message:4.0.0 and is removed from
   *   private_message:5.0.0. Instead, you should just use the plugin definition
   *   'name' value $plugin->getPluginDefinition()['name'].
   *
   * @see https://www.drupal.org/node/3501696
   */
  public function getId(): string;

  /**
   * Build the section of the form as it will appear on the settings page.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The Drupal form state.
   *
   * @return array
   *   A render array containing the form elements this plugin provides.
   */
  public function buildForm(FormStateInterface $formState): array;

  /**
   * Validate this section of the form.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The Drupal form state.
   */
  public function validateForm(array &$form, FormStateInterface $formState): void;

  /**
   * Handle submission of the form added to the settings page.
   *
   * @param array $values
   *   An array of values for form elements added by this plugin.
   */
  public function submitForm(array $values);

}

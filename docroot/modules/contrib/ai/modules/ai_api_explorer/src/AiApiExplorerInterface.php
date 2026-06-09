<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\GenericType\FileBaseInterface;
use Drupal\ai\Plugin\ProviderProxy;

/**
 * Interface for ai_api_explorer plugins.
 */
interface AiApiExplorerInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Allow forms to carry out additional checks before they display.
   *
   * @return bool
   *   Whether the form is active and so can display to users.
   */
  public function isActive(): bool;

  /**
   * Helper to return a human-readable label for the form.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable label for the form.
   */
  public function getLabel(): TranslatableMarkup;

  /**
   * Helper to return a human-readable description for the form.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable description of the form.
   */
  public function getDescription(): TranslatableMarkup;

  /**
   * Helper to allow individual forms to provide advanced access restrictions.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in user.
   *
   * @return bool
   *   TRUE to grant access, FALSE to deny.
   */
  public function hasAccess(AccountInterface $account): bool;

  /**
   * Returns the correct AJAX method from the AiApiExplorerForm.
   *
   * @return string
   *   The AJAX method name. MUST be a method on the form.
   */
  public function getAjaxResponseId(): string;

  /**
   * Builds the form to display to users.
   *
   * @param array $form
   *   The Drupal form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState.
   *
   * @return array
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array;

  /**
   * Helper to get the response from the AI.
   *
   * @param array $form
   *   The Drupal form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The FormState.
   *
   * @return array
   *   The processed response.
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array;

  /**
   * Helper to return a standard render template for the example code.
   *
   * @return array
   *   A Drupal render array template for a details element.
   */
  public function getCodeExampleTemplate(): array;

  /**
   * Helper to provide a structure for the AI Explorer Form.
   *
   * @param array $form
   *   The form array.
   * @param string $ajax_id
   *   The ID used in the AJAX response call.
   * @param string $layout
   *   Either 'two columns' (default), or any other value for three columns.
   *
   * @return array
   *   A form template that can be populated with the form elements.
   */
  public function getFormTemplate(array $form, string $ajax_id, string $layout = 'two_columns'): array;

  /**
   * Helper to describe the provider config as a string.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   *
   * @return string
   *   The string description of the config.
   */
  public function addProviderCodeExample(AiProviderInterface|ProviderProxy $provider):string;

  /**
   * Helper to generate a file from an AJAX form submit.
   *
   * @param string $type
   *   The type of file to generate.
   *
   * @return \Drupal\ai\OperationType\GenericType\AudioFile|\Drupal\ai\OperationType\GenericType\ImageFile|null
   *   The generated file or NULL on error.
   */
  public function generateFile(string $type = 'audio'): FileBaseInterface|null;

  /**
   * Helper in case the plugin needs to do anything on submit.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form State.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void;

}

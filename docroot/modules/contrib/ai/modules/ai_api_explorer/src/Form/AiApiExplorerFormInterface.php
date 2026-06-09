<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface to identify all forms used by the AI API Explorer.
 *
 * @ingroup form_api
 */
interface AiApiExplorerFormInterface extends FormInterface, ContainerInjectionInterface {

  /**
   * Rebuild the form with the plugin's AJAX response.
   *
   * @param array $form
   *   The current plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated section of the form.
   */
  public function ajaxResponse(array &$form, FormStateInterface $form_state): array;

}

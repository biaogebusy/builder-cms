<?php

namespace Drupal\ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for the AI Prompts collection page.
 *
 * Handles the redirect to add the first AI Prompt Type when no types exist,
 * and delegates to the entity list builder when types are available.
 */
class AiPromptCollectionController extends ControllerBase {

  /**
   * Renders the AI Prompts collection or redirects to add a prompt type.
   *
   * When no AI Prompt Types exist, the user is redirected directly to the
   * "Add AI Prompt Type" form. This avoids a confusing empty state where the
   * user has to figure out that prompt types need to be created first.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A render array for the entity list, or a redirect response.
   */
  public function collection(): array|RedirectResponse {
    $prompt_types = $this->entityTypeManager()
      ->getStorage('ai_prompt_type')
      ->loadMultiple();

    // When no prompt types exist, redirect the user directly to add one.
    if (empty($prompt_types)) {
      $url = Url::fromRoute('entity.ai_prompt_type.add_form')->toString();
      $this->messenger()->addStatus($this->t('To create a reusable AI Prompt, you must first introduce a prompt type for it.'));
      return new RedirectResponse($url);
    }

    // Prompt types exist, render the normal entity list with help text.
    $list_builder = $this->entityTypeManager()->getListBuilder('ai_prompt');
    return $list_builder->render();
  }

}

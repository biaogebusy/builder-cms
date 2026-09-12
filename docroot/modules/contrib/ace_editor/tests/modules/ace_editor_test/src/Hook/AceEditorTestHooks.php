<?php

namespace Drupal\ace_editor_test\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for ace_editor_test.
 */
class AceEditorTestHooks {

  public function __construct(
    protected RequestStack $requestStack,
  ) {
  }

  /**
   * Implements hook_form_alter().
   *
   * Disables the body field when the ace_test_disable_body query parameter is
   * present, so a functional test can verify that a disabled field renders a
   * read-only Ace editor (issue #3046914).
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id) {
    $request = $this->requestStack->getCurrentRequest();
    if ($request && $request->query->get('ace_test_disable_body') && isset($form['body'])) {
      $form['body']['#disabled'] = TRUE;
    }
  }

}

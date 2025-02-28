<?php

namespace Drupal\workspace\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;

/**
 * Switcher for to activate a different workspace.
 *
 * This is a separate form for each workspace rather than one big form with
 * many buttons for scaling reasons. For example, this form may show up in a
 * toolbar. We may want to show just a subset of workspaces to switch to, maybe
 * access control, etc. This approach keeps that logic out of the switching
 * process itself.
 */
class WorkspaceSwitcherForm extends WorkspaceActivateFormBase {

  /**
   * Hack to allow us to show this form multiple times on a page.
   *
   * @see https://www.drupal.org/node/766146
   *
   * @var int
   */
  protected static $formCounter = 1;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workspace_switcher_form_' . static::$formCounter++;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, WorkspaceInterface $workspace = NULL) {
    // @todo this form is identical to WorkspaceActivateForm except for this method; can we consolidate forms?
    $form['workspace_id'] = [
      '#type' => 'hidden',
      '#value' => $workspace->id(),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $workspace->label(),
    ];

    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    if ($active_workspace->id() === $workspace->id()) {
      $form['submit']['#attributes']['class'] = ['is-active'];
    }

    return $form;
  }

}

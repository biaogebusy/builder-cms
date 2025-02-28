<?php


namespace Drupal\workspace\Element;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\workspace\Form\WorkspaceSwitcherForm;

/**
 * Service for hooks and utilities related to Toolbar integration.
 */
class Toolbar implements TrustedCallbackInterface {

  /**
   * Prerender callback; Adds the workspace switcher forms to the render array.
   *
   * @param array $element
   *
   * @return array
   *   The modified $element.
   */
  public static function preRenderWorkspaceSwitcherForms(array $element) {
    foreach (self::allWorkspaces() as $workspace) {
      $element['workspace_forms']['workspace_' . $workspace->getMachineName()] = \Drupal::service('form_builder')->getForm(WorkspaceSwitcherForm::class, $workspace);
    }
    return $element;
  }

  /**
   * Returns a list of all defined and accessible workspaces.
   *
   * Note: This assumes that the total number of workspaces on the site is
   * very small.  If it's actually large this method will have memory issues.
   *
   * @return WorkspaceInterface[]
   */
  protected static function allWorkspaces() {
    return array_filter(\Drupal::entityTypeManager()->getStorage('workspace')->loadMultiple(), function (WorkspaceInterface $workspace) {
      return $workspace->isPublished() && !$workspace->getQueuedForDelete() && $workspace->access('view', \Drupal::currentUser());
    });
  }

  public static function trustedCallbacks() {
    // TODO: Implement trustedCallbacks() method.
    return ['preRenderWorkspaceSwitcherForms'];
  }
}

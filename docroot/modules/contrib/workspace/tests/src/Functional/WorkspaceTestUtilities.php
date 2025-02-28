<?php

namespace Drupal\Tests\workspace\Functional;

use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\node\Entity\NodeType;
use Drupal\workspace\Entity\WorkspacePointer;

/**
 * Utility methods for use in BrowserTestBase tests.
 *
 * This trait will not work if not used in a child of BrowserTestBase.
 */
trait WorkspaceTestUtilities {

  /**
   * Loads a single workspace by its label.
   *
   * The UI approach to creating a workspace doesn't make it easy to know what
   * the ID is, so this lets us make paths for a workspace after it's created.
   *
   * @param $label
   *   The label of the workspace to load.
   *
   * @return \Drupal\multiversion\Entity\WorkspaceInterface
   */
  protected function getOneWorkspaceByLabel($label) {
    return $this->getOneEntityByLabel('workspace', $label);
  }

  /**
   * Loads a single entity by its label.
   *
   * The UI approach to creating an entity doesn't make it easy to know what
   * the ID is, so this lets us make paths for an entity after it's created.
   *
   * @param string $type
   *   The type of entity to load.
   * @param $label
   *   The label of the entity to load.
   *
   * @return \Drupal\multiversion\Entity\WorkspaceInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getOneEntityByLabel($type, $label) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $etm */
    $etm = \Drupal::service('entity_type.manager');

    $property = $etm->getDefinition($type)->getKey('label');

    /** @var \Drupal\multiversion\Entity\WorkspaceInterface $bears */
    $entity_list = $etm->getStorage($type)->loadByProperties([$property => $label]);

    $entity = current($entity_list);

    if (!$entity) {
      $this->fail("No {$type} entity named {$label} found.");
    }

    return $entity;
  }

  /**
   * Creates a new Workspace through the UI.
   *
   * @param string $label
   *   The label of the workspace to create.
   * @param string $machine_name
   *   The machine name of the workspace to create.
   *
   * @return \Drupal\multiversion\Entity\WorkspaceInterface
   *   The workspace that was just created.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function createWorkspaceThroughUI($label, $machine_name) {
    $this->drupalGet('/admin/structure/workspace/add');

    $session = $this->getSession();
    $this->assertSession()->statusCodeEquals(200);

    $page = $session->getPage();
    $page->fillField('label', $label);
    $page->fillField('machine_name', $machine_name);
    $page->findButton(t('Save'))->click();

    $session->getPage()->hasContent("$label ($machine_name)");

    return $this->getOneWorkspaceByLabel($label);
  }

  /**
   * Adds the workspace switcher block to the site.
   *
   * This is necessary for switchToWorkspace() to function correctly.
   */
  protected function setupWorkspaceSwitcherBlock() {
    // Add the block to the sidebar.
    $this->drupalPlaceBlock('workspace_switcher_block', [
      'id' => 'workspaceswitcher',
      'region' => 'sidebar_first',
      'label' => 'Workspace switcher',
    ]);

    // Confirm the block shows on the front page.
    $this->drupalGet('<front>');
    $page = $this->getSession()->getPage();

    $this->assertTrue($page->hasContent('Workspace switcher'));
  }

  /**
   * Sets a given workspace as "active" for subsequent requests.
   *
   * This assumes that the switcher block has already been setup by calling
   * setupWorkspaceSwitcherBlock().
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace to set active.
   */
  protected function switchToWorkspace(WorkspaceInterface $workspace) {
    // Switch the test runner's context to the specified workspace.
    \Drupal::service('workspace.manager')->setActiveWorkspace($workspace);

    // Switch the system under test to the specified workspace.
    $this->drupalGet('<front>');
    $this->getSession()->getPage()->findButton($workspace->label())->click();

    // If we don't do both of those, test runner utility methods will not be
    // run in the same workspace as the system under test, and you'll be left
    // wondering why your test runner cannot find content you just created.
  }

  /**
   * Creates a new node type.
   *
   * @param string $label
   *   The human-readable label of the type to create.
   * @param string $machine_name
   *   The machine name of the type to create.
   */
  protected function createNodeType($label, $machine_name) {
    $node_type = NodeType::create([
      'type' => $machine_name,
      'label' => $label,
    ]);
    $node_type->save();
  }

  /**
   * Creates a node by "clicking" buttons.
   *
   * @param string $label
   * @param string $bundle
   *
   * @return \Drupal\multiversion\Entity\WorkspaceInterface
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function createNodeThroughUI($label, $bundle) {
    $this->drupalGet('/node/add/' . $bundle);

    $session = $this->getSession();
    $this->assertSession()->statusCodeEquals(200);

    $page = $session->getPage();
    $page->fillField('Title', $label);
    $page->findButton(t('Save'))->click();

    $session->getPage()->hasContent("{$label} has been created");

    return $this->getOneEntityByLabel('node', $label);
  }

  /**
   * Returns a pointer to the specified workspace.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace for which we want a pointer.
   *
   * @return \Drupal\workspace\Entity\WorkspacePointerInterface
   *   The pointer to the provided workspace.
   *
   * @deprecated
   *   Use \Drupal\workspace\Entity\WorkspacePointer::loadFromWorkspace().
   */
  protected function getPointerToWorkspace(WorkspaceInterface $workspace) {
    return WorkspacePointer::loadFromWorkspace($workspace);
  }

  /**
   * Determine if the content list has an entity's label.
   *
   * This assertion can be used to validate a particular entity exists in the
   * current workspace.
   */
  protected function isLabelInContentOverview($label) {
    $this->drupalGet('/admin/content');
    $session = $this->getSession();
    $this->assertSession()->statusCodeEquals(200);
    $page = $session->getPage();
    return $page->hasContent($label);
  }

}

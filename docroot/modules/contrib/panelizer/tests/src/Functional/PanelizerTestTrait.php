<?php

namespace Drupal\Tests\panelizer\Functional;

use Drupal\user\Entity\User;

/**
 * Contains helper methods for writing functional tests of Panelizer.
 */
trait PanelizerTestTrait {

  /**
   * Log in as user 1.
   */
  protected function loginUser1() {
    // Log in as user 1.
    /* @var \Drupal\user\Entity\User $account */
    $account = User::load(1);
    $password = 'foo';
    $account->setPassword($password)->save();
    // Support old and new tests.
    $account->passRaw = $password;
    $account->pass_raw = $password;
    $this->drupalLogin($account);
  }

  /**
   * Prep a content type for use with these tests.
   *
   * @param string $content_type
   *   The content type, i.e. the node bundle ID, to configure; defaults to
   *  'page'.
   */
  protected function setupContentType($content_type = 'page') {
    // Log in as user 1.
    $this->loginUser1();

    // Create the content type.
    $this->drupalCreateContentType(['type' => $content_type, 'name' => 'Page']);

    // Allow each node to have a customized display.
    $this->panelize($content_type, NULL, ['panelizer[custom]' => TRUE]);

    // Logout so that a new user can log in.
    $this->drupalLogout();
  }

  /**
   * Create a test node.
   *
   * @param string $type
   *   The entity type to create, defaults to 'page'.
   *
   * @return object
   *   An example node.
   */
  protected function createTestNode($type = 'page') {
    // Create a test node.
    return $this->drupalCreateNode([
      'title' => t('Hello, world!'),
      'type' => $type,
    ]);
  }

  /**
   * Panelizes a node type's default view display.
   *
   * @param string $content_type
   *   The content type, i.e. the node bundle ID, to configure; defaults to
   *  'page'.
   * @param string $display
   *   (optional) The view mode to work on.
   * @param array $values
   *   (optional) Additional form values.
   */
  protected function panelize($content_type = 'page', $display = NULL, array $values = []) {
    $this->drupalGet("admin/structure/types");
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet("admin/structure/types/manage/{$content_type}");
    $this->assertSession()->statusCodeEquals(200);

    $path = "admin/structure/types/manage/{$content_type}/display";
    if (!empty($display)) {
      $path .= '/' . $display;
    }
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'panelizer[enable]' => TRUE,
    ] + $values;
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);

    \Drupal::service('entity_display.repository')->getFormDisplay('node', $content_type)
      ->setComponent('panelizer', [
        'type' => 'panelizer',
      ])
      ->save();
  }

  /**
   * Unpanelizes a node type's default view display.
   *
   * Panelizer is disabled for the display, but its configuration is retained.
   *
   * @param string $content_type
   *   The content type, i.e. the node bundle ID, to configure; defaults to
   *  'page'.
   * @param string $display
   *   (optional) The view mode to work on.
   * @param array $values
   *   (optional) Additional form values.
   */
  protected function unpanelize($content_type = 'page', $display = NULL, array $values = []) {
    $this->drupalGet("admin/structure/types/manage/{$content_type}/display/{$display}");
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'panelizer[enable]' => FALSE,
    ] + $values;
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);

    \Drupal::service('entity_display.repository')->getFormDisplay('node', $content_type)
      ->removeComponent('panelizer')
      ->save();
  }

  /**
   *
   *
   * @param string $content_type
   *   The content type, i.e. the node bundle ID, to configure; defaults to
   *  'page'.
   */
  protected function addPanelizerDefault($content_type = 'page', $display = 'default') {
    $label = $this->getRandomGenerator()->word(16);
    $id = strtolower($label);
    $default_id = "node__{$content_type}__{$display}__{$id}";
    $options = [
      'query' => [
        'js' => 'nojs',
      ],
    ];
    $path = "admin/structure/types/manage/{$content_type}/display";
    if (!empty($display)) {
      $path .= '/' . $display;
    }
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->clickLink('Add a new Panelizer default display');

    // Step 1: Enter the default's label and ID.
    $edit = [
      'id' => $id,
      'label' => $label,
    ];
    $this->submitForm($edit, 'Next');
    $this->assertSession()->statusCodeEquals(200);

    // Step 2: Define contexts.
    $this->assertSession()->addressEquals("admin/structure/panelizer/add/{$default_id}/contexts", $options);
    $this->submitForm([], 'Next');
    $this->assertSession()->statusCodeEquals(200);

    // Step 3: Select layout.
    $this->assertSession()->addressEquals("admin/structure/panelizer/add/{$default_id}/layout", $options);
    $this->submitForm([], 'Next');
    $this->assertSession()->statusCodeEquals(200);

    // Step 4: Select content.
    $this->assertSession()->addressEquals("admin/structure/panelizer/add/{$default_id}/content", $options);
    $this->submitForm([], 'Finish');
    $this->assertSession()->statusCodeEquals(200);

    return $id;
  }

  /**
   * Deletes a Panelizer default.
   *
   * @param string $content_type
   *   The content type, i.e. the node bundle ID, to configure; defaults to
   *  'page'.
   * @param string $display
   *   (optional) The view mode to work on.
   * @param string $id
   *   (optional) The default ID.
   */
  protected function deletePanelizerDefault($content_type = 'page', $display = 'default', $id = 'default') {
    $this->drupalGet("admin/structure/panelizer/delete/node__{$content_type}__{$display}__{$id}");
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Confirm');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Asserts that a Panelizer default exists.
   *
   * @param string $content_type
   *   The content type, i.e. the node bundle ID, to configure; defaults to
   *  'page'.
   * @param string $display
   *   (optional) The view mode to work on.
   * @param string $id
   *   (optional) The default ID.
   */
  protected function assertDefaultExists($content_type = 'page', $display = 'default', $id = 'default') {
    $settings = \Drupal::service('entity_display.repository')->getViewDisplay('node', $content_type)
      ->getThirdPartySettings('panelizer');

    $display_exists = isset($settings['displays'][$id]);
    $this->assertTrue($display_exists);
  }

  /**
   * Asserts that a Panelizer default does not exist.
   *
   * @param string $content_type
   *   The content type, i.e. the node bundle ID, to configure; defaults to
   *  'page'.
   * @param string $display
   *   (optional) The view mode to work on.
   * @param string $id
   *   The default ID.
   */
  protected function assertDefaultNotExists($content_type = 'page', $display = 'default', $id = 'default') {
    $settings = \Drupal::service('entity_display.repository')->getViewDisplay('node', $content_type, $display)
      ->getThirdPartySettings('panelizer');

    $display_exists = isset($settings['displays'][$id]);
    $this->assertFalse($display_exists);
  }

}

<?php

namespace Drupal\Tests\panelizer\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Confirm the defaults functionality works.
 *
 * @group panelizer
 */
class PanelizerDefaultsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'node',
    'user',
    'panelizer',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createContentType(['type' => 'page']);
  }

  public function test() {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    $account = $this->createUser(['administer node display']);
    $this->drupalLogin($account);

    // Get all enabled view modes machine names for page.
    $view_modes = array_keys(\Drupal::service('entity_display.repository')
                               ->getViewModeOptionsByBundle('node', 'page'));
    foreach ($view_modes as $view_mode_name) {
      $this->drupalGet("/admin/structure/types/manage/page/display/$view_mode_name");
      $assert_session->statusCodeEquals(200);

      // Enable Panelizer via the API, and assert that the checkboxes are
      // present and have the expected states.
      $this->container->get('panelizer')
        ->setPanelizerSettings('node', 'page', $view_mode_name, [
          'enable' => TRUE,
          'allow' => TRUE,
          'custom' => TRUE,
          'default' => 'default',
        ]);
      $session->reload();
      $assert_session->statusCodeEquals(200);
      $assert_session->checkboxChecked('panelizer[enable]');
      $assert_session->checkboxChecked('panelizer[allow]');
      $assert_session->checkboxChecked('panelizer[custom]');

      // Disable customization and assert that it can no longer be re-enabled
      // in the UI.
      $page->uncheckField('panelizer[custom]');
      $page->pressButton('Save');
      $assert_session->statusCodeEquals(200);
      $assert_session->checkboxChecked('panelizer[enable]');
      $assert_session->checkboxChecked('panelizer[allow]');
      $assert_session->fieldNotExists('panelizer[custom]');

      // Disable Panelizer and assert that it cannot be re-enabled in the UI.
      $page->uncheckField('panelizer[enable]');
      $page->pressButton('Save');
      $assert_session->statusCodeEquals(200);
      $assert_session->fieldNotExists('panelizer[enable]');
      $assert_session->fieldNotExists('panelizer[allow]');
      $assert_session->fieldNotExists('panelizer[custom]');
    }
  }

}

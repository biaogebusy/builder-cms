<?php

namespace Drupal\Tests\dropzonejs\FunctionalJavascript;

use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;

/**
 * Test dropzonejs EB Widget.
 *
 * @group dropzonejs
 */
class DropzoneJsEbWidgetTest extends DropzoneJsWebDriverTestBase {

  use FieldUiTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'media',
    'menu_ui',
    'path',
    'dropzonejs_test',
  ];

  /**
   * Permissions for user that will be logged-in for test.
   *
   * @var array
   */
  protected static $userPermissions = [
    'access dropzonejs_eb_standalone_test entity browser pages',
    'access dropzonejs_eb_test entity browser pages',
    'create dropzonejs_test content',
    'dropzone upload files',
    'access content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $account = $this->drupalCreateUser(static::$userPermissions);
    $this->drupalLogin($account);
  }

  /**
   * Tests the add widget with iframe form.
   */
  public function testUploadFile() {
    $this->drupalGet('node/add/dropzonejs_test');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_dropzonejs_eb_test');
    $this->dropFile();
    $this->getSession()->getPage()->pressButton('Select entities');

    // Switch back to the main page.
    $this->waitForAjaxToFinish();
    $this->getSession()->switchToIFrame();
    // For some reason we have to wait here for the markup to show up regardless
    // of the waitForAjaxToFinish above.
    sleep(2);
    $this->assertSession()->elementContains('xpath', '//div[contains(@class, "entities-list")]/div[contains(@class, "label")]', 'notalama.jpg');
  }

  /**
   * Tests that the add widget with iframe form handles invalid file names.
   */
  public function testUploadInvalidFile() {
    $this->config('dropzonejs.settings')->set('filename_transliteration', TRUE)->save();
    $this->drupalGet('dropzonejs-eb-standalone-test');
    $this->dropFile('.dotfile');
    $this->getSession()->getPage()->pressButton('Select entities');
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $all_files = $file_storage->loadMultiple();
    // The first file is from '\Drupal\Tests\dropzonejs\FunctionalJavascript\DropzoneJsWebDriverTestBase::getFile'
    // The second file then should have no dot, because
    // 'SecurityFileUploadEventSubscriber' did run.
    $this->assertEquals('.dotfile.jpg', $all_files[1]->get('filename')->value);
    $this->assertEquals('dotfile.jpg', $all_files[2]->get('filename')->value);
  }

  /**
   * Test widget inheritance.
   */
  public function testWidgetInheritance() {
    $this->drupalGet('node/add/dropzonejs_test');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_dropzonejs_eb_test');
    $drupal_settings = $this->getDrupalSettings();
    $this->assertEquals('.png,.gif,.jpg,.jpeg', $drupal_settings['dropzonejs']['instances']['edit-upload']['acceptedFiles']);

    $config = \Drupal::configFactory()->getEditable('entity_browser.browser.dropzonejs_eb_test');
    $config->set('widgets.44b1e6ea-637d-4dd6-b79e-edeefc546c1c.settings.inherit_settings', FALSE);
    $config->save();

    $this->drupalGet('node/add/dropzonejs_test');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_dropzonejs_eb_test');
    $drupal_settings = $this->getDrupalSettings();
    $this->assertEquals('.jpg,.jpeg,.gif,.png,.txt,.doc,.xls,.pdf,.ppt,.pps,.odt,.ods,.odp', $drupal_settings['dropzonejs']['instances']['edit-upload']['acceptedFiles']);
  }

}

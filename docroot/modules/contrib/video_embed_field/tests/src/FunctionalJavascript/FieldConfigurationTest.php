<?php

namespace Drupal\Tests\video_embed_field\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\video_embed_field\Functional\AdminUserTrait;
use Drupal\Tests\video_embed_field\Functional\EntityDisplaySetupTrait;

/**
 * Integration test for the field configuration form.
 *
 * @group video_embed_field
 */
class FieldConfigurationTest extends WebDriverTestBase {

  use EntityDisplaySetupTrait;
  use AdminUserTrait;

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
    'video_embed_field',
  ];

  /**
   * Test the field configuration form.
   */
  public function testFieldConfiguration() {
    $this->drupalLogin($this->createAdminUser());
    $this->createContentType(['type' => 'page', 'name' => 'Page']);
    $this->drupalGet('admin/structure/types/manage/page/fields/add-field');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    if ($this->coreVersion('11.2')) {
      // Field creation UI has changed in Drupal 11.2.
      $this->clickLink('Video Embed');
      $assert_session->assertWaitOnAjaxRequest();
      $assert_session->waitForText('Add field: Video Embed');
      $page->fillField('label', 'Video Embed');
      $buttons = $assert_session->elementExists('css', '.ui-dialog-buttonpane');
      $buttons->pressButton('Continue');
      $assert_session->assertWaitOnAjaxRequest();
      $page->checkField('Required field');
      $page->checkField('Vimeo');
      $page->checkField('YouTube');
      $page->checkField('YouTube Playlist');
      $page->checkField('Set default value');
      $page->fillField('Video Embed', 'http://example.com');
      $buttons = $assert_session->elementExists('css', '.ui-dialog-buttonpane');
      $buttons->pressButton('Save');
      $this->assertTrue($assert_session->waitForText('Could not find a video provider to handle the given URL.'));
      $page->fillField('Video Embed', 'https://www.youtube.com/watch?v=XgYu7-DQjDQ');
      $buttons = $assert_session->elementExists('css', '.ui-dialog-buttonpane');
      $buttons->pressButton('Save');
      $this->assertTrue($assert_session->waitForText('Saved Video Embed configuration.'));
    }
    else {
      $page->selectFieldOption('new_storage_type', 'video_embed_field');
      $page->pressButton('Continue');
      $page->fillField('label', 'Video Embed');
      $page->pressButton('Continue');
      $page->fillField('Required field', TRUE);
      $page->fillField('Vimeo', TRUE);
      $page->fillField('YouTube', TRUE);
      $page->fillField('YouTube Playlist', TRUE);
      $page->fillField('Set default value', TRUE);
      $page->fillField('Video Embed', 'http://example.com');
      $page->pressButton('Save settings');
      $this->assertSession()->pageTextContains('Could not find a video provider to handle the given URL.');
      $page->fillField('Video Embed', 'https://www.youtube.com/watch?v=XgYu7-DQjDQ');
      $page->pressButton('Save settings');
      $this->assertSession()->pageTextContains('Saved Video Embed configuration.');
    }

  }

  /**
   * Checks the core version.
   *
   * @param string $version
   *   The core version, for example 10.3.
   *
   * @return bool
   *   Whether the core version is higher than the requested one.
   */
  protected function coreVersion(string $version): bool {
    return version_compare(\Drupal::VERSION, $version, '>=');
  }

}

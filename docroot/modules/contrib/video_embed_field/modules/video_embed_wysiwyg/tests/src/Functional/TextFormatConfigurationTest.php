<?php

namespace Drupal\Tests\video_embed_wysiwyg\Functional;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\video_embed_field\Functional\AdminUserTrait;

/**
 * Test the format configuration form.
 *
 * @group video_embed_wysiwyg
 */
class TextFormatConfigurationTest extends BrowserTestBase {

  use AdminUserTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'video_embed_field',
    'video_embed_wysiwyg',
    'editor',
    'ckeditor5',
    'field_ui',
    'node',
    'image',
  ];

  /**
   * The URL for the filter format.
   *
   * @var string
   */
  protected $formatUrl = '/admin/config/content/formats/manage/filtered_html';

  /**
   * The name of filter format.
   *
   * @var string
   */
  protected $formatName = 'filtered_html';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->createAdminUser());

    // CKE5 does not work plain text hence create new format.
    FilterFormat::create([
      'format' => $this->formatName,
      'name' => $this->formatName,
    ])->save();
    Editor::create([
      'format' => $this->formatName,
      'editor' => 'ckeditor5',
    ])->setImageUploadSettings(['status' => FALSE])->save();

    // Setup the filter to have an editor.
    $this->drupalGet($this->formatUrl);
    $page = $this->getSession()->getPage();
    $page->checkField('roles[authenticated]');
    $this->submitForm([], 'Save configuration');
  }

  /**
   * Test both the input filter and button need to be enabled together.
   */
  public function testFormatConfiguration() {
    // Save the settings with the filter enabled, with button.
    $this->drupalGet($this->formatUrl);
    $page = $this->getSession()->getPage();
    $page->fillField('Video Embed WYSIWYG', TRUE);
    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains("The text format {$this->formatName} has been updated.");
  }

  /**
   * Test the URL filter weight not in correct order.
   */
  public function testUrlWeightOrder() {
    $editor = Editor::load($this->formatName);
    $settings = $editor->getSettings();
    $settings['toolbar']['items'][] = '|';
    $settings['toolbar']['items'][] = 'videoEmbed';
    $editor->setSettings($settings)->save();
    $format = FilterFormat::load($this->formatName);
    $format->setFilterConfig('video_embed_wysiwyg', ['weight' => 10]);
    $format->setFilterConfig('filter_url', ['weight' => -10]);
    $format->save();
    $this->drupalGet($this->formatUrl);
    $page = $this->getSession()->getPage();
    $page->fillField('Video Embed WYSIWYG', TRUE);
    $page->fillField('Convert URLs into links', TRUE);
    $page->fillField('Limit allowed HTML tags and correct faulty HTML', TRUE);
    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The "Video Embed WYSIWYG" filter must run before the "Convert URLs into links" filter to function correctly.');
  }

  /**
   * Test the URL filter weight is in the correct order.
   */
  public function testHtmlFilterWeightOrder() {
    $editor = Editor::load($this->formatName);
    $settings = $editor->getSettings();
    $settings['toolbar']['items'][] = '|';
    $settings['toolbar']['items'][] = 'videoEmbed';
    $editor->setSettings($settings)->save();
    $format = FilterFormat::load($this->formatName);
    $format->setFilterConfig('video_embed_wysiwyg', ['weight' => -10]);
    $format->setFilterConfig('filter_url', ['weight' => 10]);
    $format->save();
    $this->drupalGet($this->formatUrl);
    $page = $this->getSession()->getPage();
    $page->fillField('Video Embed WYSIWYG', TRUE);
    $page->fillField('Convert URLs into links', TRUE);
    $page->fillField('Limit allowed HTML tags and correct faulty HTML', TRUE);
    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains("The text format {$this->formatName} has been updated.");
  }

  /**
   * Test the dialog defaults can be set and work correctly.
   */
  public function testDialogDefaultValues() {
    $editor = Editor::load($this->formatName);
    $settings = $editor->getSettings();
    $settings['toolbar']['items'][] = '|';
    $settings['toolbar']['items'][] = 'videoEmbed';
    $settings['toolbar']['items'][] = 'sourceEditing';
    $settings['plugins']['video_embed_wysiwyg_video_embed']['defaults']['children']['autoplay'] = FALSE;
    $settings['plugins']['video_embed_wysiwyg_video_embed']['defaults']['children']['responsive'] = FALSE;
    $settings['plugins']['video_embed_wysiwyg_video_embed']['defaults']['children']['width'] = '123';
    $settings['plugins']['video_embed_wysiwyg_video_embed']['defaults']['children']['height'] = '456';
    $settings['plugins']['video_embed_wysiwyg_video_embed']['defaults']['children']['title_format'] = '@title';
    $settings['plugins']['video_embed_wysiwyg_video_embed']['defaults']['children']['title_fallback'] = TRUE;

    $editor->setSettings($settings)->save();
    $format = FilterFormat::load($this->formatName);
    $format->setFilterConfig('video_embed_wysiwyg', ['status' => 1]);
    $format->save();

    // Ensure the configured defaults show up on the modal window.
    $this->drupalGet('/video-embed-wysiwyg/dialog/' . $this->formatName);
    $this->assertSession()->fieldValueEquals('width', '123');
    $this->assertSession()->fieldValueEquals('height', '456');
    $this->assertSession()->fieldValueEquals('title_format', '@title');
    $this->assertSession()->fieldValueEquals('title_fallback', TRUE);
    $this->assertSession()->fieldValueEquals('autoplay', FALSE);
    $this->assertSession()->fieldValueEquals('responsive', FALSE);
  }

}

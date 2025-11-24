<?php

namespace Drupal\Tests\video_embed_wysiwyg\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Test the dialog form.
 *
 * @group video_embed_wysiwyg
 */
class EmbedDialogTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
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
   * An admin account for testing.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

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
  public function setUp(): void {
    parent::setUp();
    // CKE5 does not work plain text hence create new format.
    FilterFormat::create([
      'format' => $this->formatName,
      'name' => $this->formatName,
    ])->save();
    Editor::create([
      'format' => $this->formatName,
      'editor' => 'ckeditor5',
    ])->save();
    // Create admin user and login.
    $this->adminUser = $this->drupalCreateUser(array_keys($this->container->get('user.permissions')->getPermissions()));
    $this->drupalLogin($this->adminUser);

    // Create content type.
    $this->createContentType(['type' => 'page']);
    \Drupal::configFactory()->getEditable('image.settings')->set('suppress_itok_output', TRUE)->save();

    // Assert access is denied without enabling the filter.
    $this->drupalGet('/video-embed-wysiwyg/dialog/' . $this->formatName);
    $this->assertSession()->pageTextContains('Access denied');

    // Enable the filter.
    $editor = Editor::load($this->formatName);
    $settings = $editor->getSettings();
    $settings['toolbar']['items'][] = '|';
    $settings['toolbar']['items'][] = 'videoEmbed';
    $settings['toolbar']['items'][] = 'sourceEditing';
    $editor->setSettings($settings)->save();
    $this->drupalGet($this->formatUrl);
    $page = $this->getSession()->getPage();
    $page->fillField('Video Embed WYSIWYG', TRUE);
    $page->pressButton('Save configuration');
    $this->assertSession()->pageTextContains("The text format {$this->formatName} has been updated.");

    // Visit the modal again.
    $this->drupalGet('/video-embed-wysiwyg/dialog/' . $this->formatName);
    // $this->assertSession()->pageTextNotContains('Access denied');
  }

  /**
   * Test the WYSIWYG embed modal.
   */
  public function testEmbedDialog() {
    // Use the modal to embed into a page.
    $this->drupalGet('/node/add/page');
    $this->find('[data-cke-tooltip-text="Video Embed"]')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Assert all the form fields appear on the modal.
    $this->assertSession()->pageTextContains('Autoplay');
    $this->assertSession()->pageTextContains('Responsive Video');
    $this->assertSession()->pageTextContains('Video URL');

    // Attempt to submit the modal with no values.
    $this->find('input[name="video_url"]')->setValue('');
    $this->find('button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Video URL field is required.');

    // Submit the form with an invalid video URL.
    $this->find('input[name="video_url"]')->setValue('http://example.com/');
    $this->find('button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Could not find a video provider to handle the given URL.');
    $this->assertStringContainsString('http://example.com/', $this->getSession()->getPage()->getHtml());

    // Submit a valid URL.
    $this->find('input[name="video_url"]')->setValue('https://www.youtube.com/watch?v=iaf3Sl2r3jE&t=1553s');
    $this->find('input[name="autoplay"]')->setValue(TRUE);
    $this->find('input[name="responsive"]')->setValue(TRUE);
    $this->find('button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('Title', 'Video Embed Field');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Video Embed Field');
    $this->drupalGet('/node/1/edit');
    // View the source of the ckeditor and find the output.
    $this->find('.ck-source-editing-button')->click();
    $base_path = \Drupal::request()->getBasePath();
    $expected = '<p>{"preview_thumbnail":"' . rtrim($base_path, '/') . '/' . $this->publicFilesDirectory . '/styles/video_embed_wysiwyg_preview/public/video_thumbnails/iaf3Sl2r3jE.jpg","video_url":"https://www.youtube.com/watch?v=iaf3Sl2r3jE&amp;t=1553s","settings":{"responsive":1,"width":"854","height":"480","autoplay":1,"title_format":"@provider | @title","title_fallback":1},"settings_summary":["Embedded Video (Responsive, autoplaying)."]}</p>';
    $this->assertEquals($expected, $this->find('textarea[name="body[0][value]"]')->getValue());
  }

  /**
   * Test the WYSIWYG integration works with nested markup.
   */
  public function testNestedMarkup() {
    $nested_content = '<div class="nested-content">
<p>{"preview_thumbnail":"/thumb.jpg","video_url":"https://www.youtube.com/watch?v=iaf3Sl2r3jE","settings":{"responsive":1,"width":"854","height":"480","autoplay":1,"title_format":"@provider | @title","title_fallback":1},"settings_summary":["Embedded Video (Responsive, autoplaying)."]}</p>
</div>';
    $node = $this->createNode([
      'type' => 'page',
      'body' => ['value' => $nested_content],
    ]);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->find('.ck-source-editing-button')->click();
    $this->assertEquals($nested_content, $this->find('textarea[name="body[0][value]"]')->getValue());
  }

  /**
   * Find an element based on a CSS selector.
   *
   * @param string $css_selector
   *   A css selector to find an element for.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The found element or null.
   */
  protected function find($css_selector) {
    return $this->getSession()->getPage()->find('css', $css_selector);
  }

}

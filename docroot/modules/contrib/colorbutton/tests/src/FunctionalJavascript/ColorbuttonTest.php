<?php

namespace Drupal\Tests\colorbutton\FunctionalJavascript;

use Drupal\Component\Serialization\Json;
use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Colorbutton tests.
 *
 * @group colorbutton
 */
class ColorbuttonTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  static protected $modules = [
    'colorbutton',
    'colorbutton_test',
    'node',
    'filter',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'format' => 'full_html',
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getViewDisplay('node', 'article')
      ->setComponent('body')
      ->save();
    $display_repository->getFormDisplay('node', 'article')
      ->setComponent('body')
      ->save();

    $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article colorbutton',
      'nid' => 1,
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => 'full_html',
        ],
      ],
    ])->save();

    $permissions = [
      'bypass node access',
      'administer site configuration',
      'use text format full_html',
      'administer filters',
    ];
    $user = $this->createUser($permissions);
    $this->drupalLogin($user);
  }

  /**
   * Tests colorbutton.
   */
  public function testColorbutton() {
    // Verify that article are displayed.
    $this->drupalGet('/node/1');
    $this->assertSession()->pageTextContains('Article colorbutton');

    // Verify that the textcolor button is loaded in CKEditor.
    $this->drupalGet('/admin/config/content/formats/manage/full_html');
    $this->assertSession()->elementExists('css', '.ckeditor-button');
    $json_encode = function ($html) {
      return trim(Json::encode($html), '"');
    };
    $markup = $json_encode(\Drupal::service('file_url_generator')->generateString('libraries/colorbutton/icons/textcolor.png'));
    $this->assertSession()->responseContains($markup);

    // Asserts color buttons is present in the toolbar.
    $this->drupalGet('/node/1/edit');
    $this->assertSession()->elementExists('css', '#cke_edit-body-0-value .cke_button__textcolor');
    $this->assertSession()->elementExists('css', '#cke_edit-body-0-value .cke_button__bgcolor');

    // Asserts the cke panel opens when clicking the color button.
    $this->assertSession()->elementNotExists('css', '.cke_panel');
    $this->click('.cke_button__textcolor');
    $this->assertNotNull($this->assertSession()->waitForElement('css', '.cke_panel'));
    $this->assertJsCondition("jQuery('.cke_panel').length > 0");
    $this->assertJsCondition("jQuery('.cke_panel').height() > 100");
    $this->click('.cke_button__bgcolor');
    $this->assertNotNull($this->assertSession()->waitForElement('css', '.cke_panel'));
  }

}
